<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pure invalidation signal — "something about this location's tickets
 * changed" — broadcast whenever a ticket is created, called, recalled,
 * completed, or canceled (see Ticket::booted()). Carries no ticket data:
 * clients that receive it re-fetch through the existing REST endpoints,
 * which already handle location scoping and serialization correctly. This
 * also keeps the channel safe to make public — there's nothing sensitive to
 * protect here.
 */
class TicketsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly string $location)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tickets.' . $this->location);
    }

    public function broadcastAs(): string
    {
        return 'TicketsUpdated';
    }

    public function broadcastWith(): array
    {
        return ['location' => $this->location];
    }
}
