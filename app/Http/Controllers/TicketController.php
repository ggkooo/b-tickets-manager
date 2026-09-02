<?php

namespace App\Http\Controllers;

use App\Jobs\PrintTicketJob;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ServiceCatalog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $location = $this->resolveLocation($request);

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
        $location = $this->resolveLocation($request);

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

    public function store(Request $request)
    {
        $location = $this->resolveLocation($request);
        $allowedTypes = ServiceCatalog::allowedTypesForLocation($location);

        $validated = $request->validate([
            'service_type' => ['required', 'string', 'in:' . implode(',', $allowedTypes)],
        ]);

        $type = $validated['service_type'];
        $prefix = ServiceCatalog::prefixFor($location, $type);

        $ticket = null;
        $attempts = 0;

        while ($ticket === null && $attempts < 5) {
            $attempts++;
            $sequence = $this->nextSequenceForPrefix($prefix, $location);
            $key = sprintf('%s-%04d', $prefix, $sequence);

            try {
                $ticket = Ticket::create([
                    'key'          => $key,
                    'location'     => $location,
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
        $ticket = $this->resolveTicket($id, $request->user()->location);

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
        $ticket = $this->resolveTicket($id, $request->user()->location);

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
        $ticket = $this->resolveTicket($id, $request->user()->location);
        $ticket->update([
            'completed' => true,
            'completed_at' => Carbon::now(),
            'completion_type' => 'completed',
        ]);

        return response()->json($ticket);
    }

    public function cancel(Request $request, $id)
    {
        $ticket = $this->resolveTicket($id, $request->user()->location);
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
    private function resolveTicket(string|int $identifier, string $location): Ticket
    {
        return is_numeric($identifier)
            ? Ticket::where('location', $location)->findOrFail($identifier)
            : Ticket::where('location', $location)
                ->where('key', $identifier)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->firstOrFail();
    }



    /**
     * Get next sequence for one prefix (N, P, E, R).
     *
     * This sequence resets daily per prefix (e.g. N-0001 every new day).
     */
    private function nextSequenceForPrefix(string $prefix, string $location): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        $today = Carbon::today();

        $maxSequence = Ticket::whereDate('created_at', $today)
            ->where('location', $location)
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

    private function resolveLocation(Request $request): string
    {
        if ($request->user()) {
            return $request->user()->location;
        }

        $rawLocation = $request->input('location', $request->header('X-UNILAB-LOCATION'));
        $location = strtolower(trim((string) $rawLocation));

        if (!in_array($location, User::allowedLocations(), true)) {
            abort(response()->json([
                'message' => 'Local invalido. Locais permitidos: ' . implode(', ', User::allowedLocations()) . '.',
            ], 422));
        }

        return $location;
    }
}
