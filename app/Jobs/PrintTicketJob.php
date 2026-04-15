<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Support\TicketPrinterConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    private Ticket $ticket;

    /**
     * Create a new job instance.
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->queue = 'printing';
        $this->tries = 3;
        $this->backoff = [60, 300]; // 1 minuto, depois 5 minutos antes dos retries
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // Garante que apenas um job de impressão por local é processado por vez
            new WithoutOverlapping("printer-location:{$this->ticket->location}"),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);

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

            // Se já tentou 3 vezes, falha definitivamente. Caso contrário, retry.
            if ($this->attempts() < 3) {
                $this->release(60 + ($this->attempts() * 60)); // Delay maior a cada retry
            } else {
                Log::critical('Falha definitiva ao imprimir ticket apos 3 tentativas', [
                    'ticket_id' => $this->ticket->id,
                    'ticket_key' => $this->ticket->key,
                    'location' => $this->ticket->location,
                    'final_error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Job de impressao falhou permanentemente', [
            'ticket_id' => $this->ticket->id,
            'ticket_key' => $this->ticket->key,
            'location' => $this->ticket->location,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Print a ticket.
     */
    private function printTicket(Ticket $ticket): void
    {
        $printerConfig = $this->resolvePrinterConfigForLocation($ticket->location);

        Log::debug('Printer config resolved for location', [
            'location' => $ticket->location,
            'config' => [
                'enabled' => $printerConfig['enabled'] ?? false,
                'connection_type' => $printerConfig['connection_type'] ?? null,
            ],
        ]);

        if (!($printerConfig['enabled'] ?? false)) {
            throw new RuntimeException('Impressao desativada. Configure a impressora para este local.');
        }

        $printerConnection = TicketPrinterConnector::resolve($printerConfig);

        Log::debug('Printer connection resolved', [
            'connection_type' => $printerConnection['connection_type'],
            'path_or_host' => $printerConnection['connection_type'] === PrinterSetting::CONNECTION_SHARED_WINDOWS
                ? $printerConnection['share_path']
                : ($printerConnection['host'] . ':' . $printerConnection['port']),
        ]);

        $profileName = (string) ($printerConfig['profile'] ?? 'simple');
        $header = (string) ($printerConfig['header'] ?? 'SENHA DE ATENDIMENTO');
        $locationLabel = $this->formatLocationLabel($ticket->location);
        $printedAt = now(config('app.timezone'))->format('d/m/Y H:i:s');

        $profile = CapabilityProfile::load($profileName);
        $connector = $printerConnection['connection_type'] === PrinterSetting::CONNECTION_SHARED_WINDOWS
            ? new WindowsPrintConnector($printerConnection['share_path'])
            : new NetworkPrintConnector($printerConnection['host'], $printerConnection['port']);
        $printer = new Printer($connector, $profile);

        try {
            Log::debug('Initializing printer');
            $printer->initialize();

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            $printer->text("================================\n");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
            $printer->text('UniLab ' . $locationLabel . "\n");
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
            ]);
        } catch (Throwable $e) {
            Log::error('Error during ticket printing', [
                'ticket_key' => $ticket->key,
                'location' => $ticket->location,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            Log::debug('Closing printer connection');
            $printer->close();
        }
    }

    /**
     * Resolve printer configuration from database.
     */
    private function resolvePrinterConfigForLocation(string $location): array
    {
        $databaseConfig = PrinterSetting::query()
            ->where('location', $location)
            ->first();

        if ($databaseConfig === null) {
            throw new RuntimeException('Impressora nao configurada para este local. Cadastre a configuracao em /api/printer-settings.');
        }

        return [
            'enabled' => $databaseConfig->enabled,
            'connection_type' => $databaseConfig->connection_type,
            'host' => $databaseConfig->host,
            'port' => $databaseConfig->port,
            'share_path' => $databaseConfig->share_path,
            'profile' => $databaseConfig->profile,
            'header' => $databaseConfig->header,
        ];
    }

    /**
     * Format location label.
     */
    private function formatLocationLabel(string $location): string
    {
        return match ($location) {
            'campus' => 'Campus',
            'centro' => 'Centro',
            default => ucfirst($location),
        };
    }
}
