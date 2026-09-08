<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Jobs\PrintTicketJob;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Support\LocationResolver;
use App\Support\ServiceCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $location = LocationResolver::resolveFromRequest($request);

        $tickets = Ticket::forLocation($location)
            ->where('completed', false)
            ->whereNull('called_at')
            ->orderByRaw('CASE WHEN service_type = ? THEN 0 ELSE 1 END', [ServiceCatalog::PRIORITY_SERVICE_TYPE])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($tickets);
    }

    public function recentlyCalled(Request $request): JsonResponse
    {
        $location = LocationResolver::resolveFromRequest($request);

        $tickets = Ticket::forLocation($location)
            ->whereNotNull('called_at')
            ->orderBy('called_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($tickets);
    }

    public function completed(Request $request): JsonResponse
    {
        $location = $request->user()->location;

        $tickets = Ticket::forLocation($location)
            ->where('completed', true)
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

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $location = $request->resolvedLocation();
        $serviceType = $request->validated('service_type');

        $ticket = $this->ticketService->createTicket($location, $serviceType);

        if ($ticket === null) {
            return response()->json([
                'message' => 'Nao foi possivel gerar a senha. Tente novamente.',
            ], 500);
        }

        PrintTicketJob::dispatch($ticket);

        return response()->json([
            'ticket' => $ticket,
            'print' => [
                'status' => 'enviando',
            ],
        ], 201);
    }

    public function call(Request $request, string|int $id): JsonResponse
    {
        $ticket = $this->ticketService->resolveTicket($id, $request->user()->location);

        if ($ticket->called_at) {
            return response()->json([
                'message' => 'Este ticket já foi chamado.',
                'ticket'  => $ticket,
            ], 422);
        }

        $this->ticketService->assignToGuiche($ticket, $request->user());

        return response()->json($ticket);
    }

    public function recall(Request $request, string|int $id): JsonResponse
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

        $this->ticketService->assignToGuiche($ticket, $request->user());

        return response()->json($ticket);
    }

    public function complete(Request $request, string|int $id): JsonResponse
    {
        $ticket = $this->ticketService->resolveTicket($id, $request->user()->location);
        $ticket->update([
            'completed' => true,
            'completed_at' => Carbon::now(),
            'completion_type' => 'completed',
        ]);

        return response()->json($ticket);
    }

    public function cancel(Request $request, string|int $id): JsonResponse
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
