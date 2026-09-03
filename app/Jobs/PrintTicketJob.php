<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketPrinterConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;
use Throwable;
use App\Models\PrinterSetting;

class PrintTicketJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 120;

    public array $backoff = [60, 180, 300, 600, 900];

    private Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->queue = 'printing';
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("printer-location:{$this->ticket->location}"))
                ->releaseAfter(15)
                ->expireAfter(180),
        ];
    }

    public function handle(): void
    {
        try {
            Log::info('Iniciando impressao de ticket via queue', [
                'ticket_id' => $this->ticket->id,
                'ticket_key' => $this->ticket->key,
                'location' => $this->ticket->location,
                'attempt' => $this->attempts(),
            ]);

            $this->printTicket($this->ticket);

            Log::info('Ticket impresso com sucesso via queue', [
                'ticket_id' => $this->ticket->id,
                'ticket_key' => $this->ticket->key,
                'location' => $this->ticket->location,
            ]);
        } catch (Throwable $e) {
            Log::error('Erro ao imprimir ticket via queue', [
                'ticket_id' => $this->ticket->id,
                'ticket_key' => $this->ticket->key,
                'location' => $this->ticket->location,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $rootCause = $exception;

        if ($exception instanceof MaxAttemptsExceededException && $exception->getPrevious() instanceof Throwable) {
            $rootCause = $exception->getPrevious();
        }

        Log::critical('Job de impressao falhou permanentemente', [
            'ticket_id' => $this->ticket->id,
            'ticket_key' => $this->ticket->key,
            'location' => $this->ticket->location,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'root_cause_error' => $rootCause->getMessage(),
            'root_cause_class' => get_class($rootCause),
        ]);
    }

    private function printTicket(Ticket $ticket): void
    {
        $printerConfigs = $this->resolvePrinterConfigsForLocation($ticket->location);

        Log::debug('Printer configs resolved for location', [
            'location' => $ticket->location,
            'count' => count($printerConfigs),
            'printers' => array_map(static fn (array $printerConfig) => [
                'name' => $printerConfig['name'] ?? null,
                'connection_type' => $printerConfig['connection_type'] ?? null,
            ], $printerConfigs),
        ]);

        $successCount = 0;
        $failures = [];

        foreach ($printerConfigs as $printerConfig) {
            try {
                $this->printTicketUsingPrinterConfig($ticket, $printerConfig);
                $successCount++;
            } catch (Throwable $exception) {
                $printerName = (string) ($printerConfig['name'] ?? 'Impressora sem nome');

                $failures[] = $printerName . ': ' . $exception->getMessage();

                Log::error('Error during ticket printing for configured printer', [
                    'ticket_key' => $ticket->key,
                    'location' => $ticket->location,
                    'printer_name' => $printerName,
                    'error' => $exception->getMessage(),
                    'error_class' => get_class($exception),
                ]);
            }
        }

        if ($successCount === 0) {
            throw new RuntimeException('Falha ao imprimir em todas as impressoras habilitadas. ' . implode(' | ', $failures));
        }

        if ($failures !== []) {
            Log::warning('Ticket printed with partial printer failures', [
                'ticket_key' => $ticket->key,
                'location' => $ticket->location,
                'successful_printers' => $successCount,
                'failed_printers' => $failures,
            ]);
        }
    }

    private function printTicketUsingPrinterConfig(Ticket $ticket, array $printerConfig): void
    {
        $printerConnection = TicketPrinterConnector::resolve($printerConfig);
        $printerName = (string) ($printerConfig['name'] ?? 'Impressora sem nome');

        Log::debug('Printer connection resolved', [
            'printer_name' => $printerName,
            'connection_type' => $printerConnection['connection_type'],
            'path_or_host' => $printerConnection['connection_type'] === PrinterSetting::CONNECTION_SHARED_WINDOWS
                ? ($printerConnection['display_share_path'] ?? TicketPrinterConnector::redactCredentials($printerConnection['share_path']))
                : ($printerConnection['host'] . ':' . $printerConnection['port']),
        ]);

        $profileName = (string) ($printerConfig['profile'] ?? 'simple');
        $header = (string) ($printerConfig['header'] ?? 'SENHA DE ATENDIMENTO');
        $institution = User::institutionForLocation($ticket->location) ?? User::INSTITUTION_UNILAB;
        $institutionLabel = User::institutionDisplayName($institution);
        $locationLabel = $this->formatLocationLabel($ticket->location);
        $printedAt = now(config('app.timezone'))->format('d/m/Y H:i:s');

        $printer = null;

        try {
            $profile = CapabilityProfile::load($profileName);
            $connector = $printerConnection['connection_type'] === PrinterSetting::CONNECTION_SHARED_WINDOWS
                ? new WindowsPrintConnector($printerConnection['share_path'])
                : new NetworkPrintConnector($printerConnection['host'], $printerConnection['port']);
            $printer = new Printer($connector, $profile);

            Log::debug('Initializing printer', [
                'printer_name' => $printerName,
            ]);
            $printer->initialize();

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            $printer->text("================================\n");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->text($institutionLabel . ' ' . $locationLabel . "\n");
            $printer->setEmphasis(false);
            $printer->text("================================\n");

            $printer->feed();
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $printer->text("SENHA\n");
            $printer->selectPrintMode();

            $printer->setTextSize(4, 4);
            $printer->text($ticket->key . "\n");
            $printer->setTextSize(1, 1);

            $printer->feed();

            $printer->text("TIPO DE ATENDIMENTO\n");
            $printer->setEmphasis(true);
            $printer->text($ticket->service_type . "\n");
            $printer->setEmphasis(false);

            $printer->text("Data/Hora: " . $printedAt . "\n");
            $printer->text("--------------------------------\n");

            $printer->text("Por favor, aguarde sua vez.\n");
            $printer->feed(2);

            $printer->cut();

            Log::info('Ticket printed successfully', [
                'ticket_key' => $ticket->key,
                'location' => $ticket->location,
                'printer_name' => $printerName,
            ]);
        } catch (Throwable $e) {
            Log::error('Error during ticket printing', [
                'ticket_key' => $ticket->key,
                'location' => $ticket->location,
                'printer_name' => $printerName,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            if ($printer !== null) {
                Log::debug('Closing printer connection', [
                    'printer_name' => $printerName,
                ]);
                $printer->close();
            }
        }
    }

    private function resolvePrinterConfigsForLocation(string $location): array
    {
        $databaseConfigs = PrinterSetting::query()
            ->where('location', $location)
            ->where('enabled', true)
            ->orderBy('name')
            ->get();

        if ($databaseConfigs->isEmpty()) {
            $hasAnyConfiguredPrinter = PrinterSetting::query()
                ->where('location', $location)
                ->exists();

            if ($hasAnyConfiguredPrinter) {
                throw new RuntimeException('Impressao desativada. Habilite ao menos uma impressora para este local.');
            }

            throw new RuntimeException('Impressora nao configurada para este local. Cadastre a configuracao em /api/printer-settings.');
        }

        return $databaseConfigs
            ->map(static fn (PrinterSetting $databaseConfig): array => [
                'id' => $databaseConfig->id,
                'name' => $databaseConfig->name,
                'enabled' => $databaseConfig->enabled,
                'connection_type' => $databaseConfig->connection_type,
                'host' => $databaseConfig->host,
                'port' => $databaseConfig->port,
                'share_path' => $databaseConfig->share_path,
                'profile' => $databaseConfig->profile,
                'header' => $databaseConfig->header,
            ])
            ->all();
    }

    private function formatLocationLabel(string $location): string
    {
        return match ($location) {
            'campus' => 'Campus',
            'centro' => 'Centro',
            default => ucfirst($location),
        };
    }
}
