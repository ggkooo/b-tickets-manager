<?php

namespace App\Http\Controllers;

use App\Support\TicketPrinterConnector;
use Illuminate\Http\Request;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;
use Throwable;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::where('completed', false)
            ->whereNull('called_at')
            ->orderByRaw("CASE WHEN service_type = 'Atendimento Preferencial' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($tickets);
    }

    public function recentlyCalled()
    {
        $tickets = Ticket::whereNotNull('called_at')
            ->orderBy('called_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($tickets);
    }

    public function completed()
    {
        $tickets = Ticket::where('completed', true)
            ->where(function ($query) {
                $query->whereDate('completed_at', Carbon::today())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('completed_at')
                            ->whereDate('updated_at', Carbon::today());
                    });
            })
            ->orderBy('completed_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|string|in:Atendimento Normal,Atendimento Preferencial,Retirada de Exames ou Entrega de Amostras',
        ]);

        $type = $validated['service_type'];

        $prefix = match ($type) {
            'Atendimento Normal'       => 'N',
            'Atendimento Preferencial' => 'P',
            'Retirada de Exames ou Entrega de Amostras' => 'E',
        };

        $ticket = null;
        $attempts = 0;

        while ($ticket === null && $attempts < 5) {
            $attempts++;
            $sequence = $this->nextSequenceForPrefix($prefix);
            $key = sprintf('%s-%04d', $prefix, $sequence);

            try {
                $ticket = Ticket::create([
                    'key'          => $key,
                    'service_type' => $type,
                    'completed'    => false,
                ]);
            } catch (QueryException $e) {
                // In case of concurrent requests, retry with the next available number.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        if ($ticket === null) {
            return response()->json([
                'message' => 'Nao foi possivel gerar a senha. Tente novamente.',
            ], 500);
        }

        try {
            $this->printTicket($ticket);

            return response()->json([
                'ticket' => $ticket,
                'print' => [
                    'status' => 'sucesso',
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Ticket criado, mas falhou na impressao automatica.', [
                'ticket_id' => $ticket->id,
                'ticket_key' => $ticket->key,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ticket' => $ticket,
                'print' => [
                    'status' => 'erro',
                    'message' => 'Ticket gerado, mas nao foi possivel imprimir automaticamente.',
                    'error' => $e->getMessage(),
                ],
            ], 201);
        }
    }

    /**
     * Call a ticket to a specific guiche (service window).
     * POST /tickets/{id}/call  — requires Bearer token
     * The authenticated user's name is used as the guiche.
     */
    public function call(Request $request, $id)
    {
        $ticket = $this->resolveTicket($id);

        if ($ticket->called_at) {
            return response()->json([
                'message' => 'Este ticket já foi chamado.',
                'ticket'  => $ticket,
            ], 422);
        }

        $ticket->update([
            'guiche'    => $request->user()->name,
            'attended_by_user_id' => $request->user()->id,
            'called_at' => Carbon::now(),
        ]);

        return response()->json($ticket);
    }

    /**
     * Recall a ticket that was already called.
     * POST /tickets/{id}/recall — requires Bearer token
     */
    public function recall(Request $request, $id)
    {
        $ticket = $this->resolveTicket($id);

        if (!$ticket->called_at) {
            return response()->json([
                'message' => 'Este ticket ainda nao foi chamado.',
                'ticket'  => $ticket,
            ], 422);
        }

        if ($ticket->completed) {
            return response()->json([
                'message' => 'Este ticket ja foi finalizado.',
                'ticket'  => $ticket,
            ], 422);
        }

        $ticket->update([
            'guiche'    => $request->user()->name,
            'attended_by_user_id' => $request->user()->id,
            'called_at' => Carbon::now(),
        ]);

        return response()->json($ticket);
    }

    public function complete($id)
    {
        $ticket = $this->resolveTicket($id);
        $ticket->update([
            'completed' => true,
            'completed_at' => Carbon::now(),
            'completion_type' => 'completed',
        ]);

        return response()->json($ticket);
    }

    public function cancel($id)
    {
        $ticket = $this->resolveTicket($id);
        $ticket->update([
            'completed' => true,
            'completed_at' => Carbon::now(),
            'completion_type' => 'canceled',
        ]);

        return response()->json($ticket);
    }

    /**
     * Resolve a ticket by numeric ID or by key (e.g. "P-QS1T").
     */
    private function resolveTicket(string|int $identifier): Ticket
    {
        return is_numeric($identifier)
            ? Ticket::findOrFail($identifier)
            : Ticket::where('key', $identifier)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->firstOrFail();
    }

    /**
     * Send one ticket to the configured network printer.
     */
    private function printTicket(Ticket $ticket): void
    {
        if (!config('services.ticket_printer.enabled', false)) {
            throw new RuntimeException('Impressao desativada. Ative TICKET_PRINTER_ENABLED=true no .env.');
        }

        $printerConnection = TicketPrinterConnector::resolve(config('services.ticket_printer', []));

        $profileName = config('services.ticket_printer.profile', 'simple');
        $header = config('services.ticket_printer.header', 'SENHA DE ATENDIMENTO');
        $printedAt = now(config('app.timezone'))->format('d/m/Y H:i:s');

        $profile = CapabilityProfile::load($profileName);
        $connector = new NetworkPrintConnector($printerConnection['host'], $printerConnection['port']);
        $printer = new Printer($connector, $profile);

        try {
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            $printer->text("================================\n");
            $printer->setEmphasis(true);
            $printer->text($header . "\n");
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
        } finally {
            $printer->close();
        }
    }

    /**
     * Get next sequence for one prefix (N, P, E, R).
     *
     * This sequence resets daily per prefix (e.g. N-0001 every new day).
     */
    private function nextSequenceForPrefix(string $prefix): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        $today = Carbon::today();

        $maxSequence = Ticket::whereDate('created_at', $today)
            ->where('key', 'like', $prefix . '-%')
            ->pluck('key')
            ->map(function (string $key) use ($pattern): ?int {
                if (!preg_match($pattern, $key, $matches)) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter(fn (?int $sequence) => $sequence !== null)
            ->max();

        return ($maxSequence ?? 0) + 1;
    }
}
