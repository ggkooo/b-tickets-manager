<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_type' => 'required|string|in:Atendimento Normal,Atendimento Preferencial,Entrega de Exames,Recebimento de Amostras',
        ]);

        $type = $validated['service_type'];

        $prefix = match ($type) {
            'Atendimento Normal'       => 'N',
            'Atendimento Preferencial' => 'P',
            'Entrega de Exames'        => 'E',
            'Recebimento de Amostras'  => 'R',
        };

        // Generate a unique key: prefix + 4 random alphanumeric chars (uppercase)
        do {
            $suffix = strtoupper(Str::random(4));
            $key = "{$prefix}-{$suffix}";
        } while (Ticket::where('key', $key)->exists());

        $ticket = Ticket::create([
            'key'          => $key,
            'service_type' => $type,
            'completed'    => false,
        ]);

        return response()->json($ticket, 201);
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
            'called_at' => Carbon::now(),
        ]);

        return response()->json($ticket);
    }

    public function complete($id)
    {
        $ticket = $this->resolveTicket($id);
        $ticket->update(['completed' => true]);

        return response()->json($ticket);
    }
    /**
     * Resolve a ticket by numeric ID or by key (e.g. "P-QS1T").
     */
    private function resolveTicket(string|int $identifier): Ticket
    {
        return is_numeric($identifier)
            ? Ticket::findOrFail($identifier)
            : Ticket::where('key', $identifier)->firstOrFail();
    }
}
