<?php

namespace App\Services;

use App\Models\Ticket;
use App\Support\ServiceCatalog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * Ticket lifecycle operations that don't belong in the HTTP layer: key
 * generation (with its daily-per-prefix sequence and retry-on-collision
 * logic) and looking a ticket up by id or by key.
 */
class TicketService
{
    private const MAX_KEY_GENERATION_ATTEMPTS = 5;

    /**
     * Creates a ticket for the given location/service type, generating a
     * daily-sequential key (e.g. "N-0001"). Retries on key collisions from
     * concurrent requests. Returns null if it couldn't find a free key
     * after a few attempts.
     */
    public function createTicket(string $location, string $serviceType): ?Ticket
    {
        $prefix = ServiceCatalog::prefixFor($location, $serviceType);
        $ticket = null;
        $attempts = 0;

        while ($ticket === null && $attempts < self::MAX_KEY_GENERATION_ATTEMPTS) {
            $attempts++;
            $sequence = $this->nextSequenceForPrefix($prefix, $location);
            $key = sprintf('%s-%04d', $prefix, $sequence);

            try {
                $ticket = Ticket::create([
                    'key'          => $key,
                    'location'     => $location,
                    'service_type' => $serviceType,
                    'completed'    => false,
                ]);
            } catch (QueryException $e) {
                // In case of concurrent requests, retry with the next available number.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        return $ticket;
    }

    /**
     * Resolve a ticket by numeric ID or by key (e.g. "P-QS1T").
     */
    public function resolveTicket(string|int $identifier, string $location): Ticket
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
     * Next sequence for one prefix (N, P, E, ...). Resets daily per prefix
     * (e.g. N-0001 every new day).
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
}
