<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Support\ServiceCatalog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class TicketService
{
    private const MAX_KEY_GENERATION_ATTEMPTS = 5;

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
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        return $ticket;
    }

    public function resolveTicket(string|int $identifier, string $location): Ticket
    {
        return is_numeric($identifier)
            ? Ticket::forLocation($location)->findOrFail($identifier)
            : Ticket::forLocation($location)
                ->where('key', $identifier)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->firstOrFail();
    }

    public function assignToGuiche(Ticket $ticket, User $user): Ticket
    {
        $ticket->update([
            'guiche' => $user->name,
            'attended_by_user_id' => $user->id,
            'called_at' => Carbon::now(),
        ]);

        return $ticket;
    }

    private function nextSequenceForPrefix(string $prefix, string $location): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
        $today = Carbon::today();

        $maxSequence = Ticket::whereDate('created_at', $today)
            ->forLocation($location)
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
