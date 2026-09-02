<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Jobs\PrintTicketJob;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Support\LocationResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function index(Request $request)
    {
        $location = LocationResolver::resolveFromRequest($request);

        $tickets = Ticket::where('completed', false)
            ->where('location', $location)
            ->whereNull('called_at')
            ->orderByRaw("CASE WHEN service_type = 'Atendimento Preferencial' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($tickets);
    }

    public function recentlyCalled(Request $request)
    {
        $location = LocationResolver::resolveFromRequest($request);

        $tickets = Ticket::whereNotNull('called_at')
            ->where('location', $location)
            ->orderBy('called_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($tickets);
    }

    public function completed(Request $request)
    {
        $location = $request->user()->location;

        $tickets = Ticket::where('completed', true)
            ->where('location', $location)
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

    public function store(StoreTicketRequest $request)
    {
        $location = $request->resolvedLocation();
        $serviceType = $request->validated('service_type');

        $ticket = $this->ticketService->createTicket($location, $serviceType);

        if ($ticket === null) {
            return response()->json([
                'message' => 'Nao foi possivel gerar a senha. Tente novamente.',
            ], 500);
        }

        // Despacha impressao para a queue (retorna imediatamente ao cliente)
        PrintTicketJob::dispatch($ticket);

        return response()->json([
            'ticket' => $ticket,
            'print' => [
                'status' => 'enviando',
            ],
        ], 201);
    }

    /**
     * Call a ticket to a specific guiche (service window).
     * POST /tickets/{id}/call  — requires Bearer token
     * The authenticated user's name is used as the guiche.
     */
    public function call(Request $request, $id)
    {
        $ticket = $this->ticketService->resolveTicket($id, $request->user()->location);

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
        $ticket = $this->ticketService->resolveTicket($id, $request->user()->location);

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

    public function complete(Request $request, $id)
    {
        $ticket = $this->ticketService->resolveTicket($id, $request->user()->location);
        $ticket->update([
            'completed' => true,
            'completed_at' => Carbon::now(),
            'completion_type' => 'completed',
        ]);

        return response()->json($ticket);
    }

    public function cancel(Request $request, $id)
    {
        $ticket = $this->ticketService->resolveTicket($id, $request->user()->location);
        $ticket->update([
            'completed' => true,
            'completed_at' => Carbon::now(),
            'completion_type' => 'canceled',
        ]);

        return response()->json($ticket);
    }
}
