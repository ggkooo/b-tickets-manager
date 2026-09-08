<?php

namespace Tests\Feature;

use App\Events\TicketsUpdated;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.api_key' => 'test-api-key']);
    }

    public function test_creating_a_ticket_broadcasts_an_update_for_its_location(): void
    {
        Event::fake([TicketsUpdated::class]);

        $this->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Atendimento Normal',
                'location' => 'campus',
            ]);

        Event::assertDispatched(TicketsUpdated::class, fn (TicketsUpdated $event) => $event->location === 'campus');
    }

    public function test_calling_a_ticket_broadcasts_an_update_for_its_location(): void
    {
        $user = User::factory()->create(['location' => 'campus']);
        Sanctum::actingAs($user);

        $ticket = Ticket::create([
            'key' => 'N-0001',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
        ]);

        Event::fake([TicketsUpdated::class]);

        $this->withHeader('X-API-KEY', 'test-api-key')
            ->postJson("/api/tickets/{$ticket->id}/call");

        Event::assertDispatched(TicketsUpdated::class, fn (TicketsUpdated $event) => $event->location === 'campus');
    }

    public function test_completing_a_ticket_broadcasts_an_update_for_its_location(): void
    {
        $user = User::factory()->create(['location' => 'campus']);
        Sanctum::actingAs($user);

        $ticket = Ticket::create([
            'key' => 'N-0002',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
            'called_at' => Carbon::now(),
        ]);

        Event::fake([TicketsUpdated::class]);

        $this->withHeader('X-API-KEY', 'test-api-key')
            ->patchJson("/api/tickets/{$ticket->id}/complete");

        Event::assertDispatched(TicketsUpdated::class, fn (TicketsUpdated $event) => $event->location === 'campus');
    }

    public function test_canceling_a_ticket_broadcasts_an_update_for_its_location(): void
    {
        $user = User::factory()->create(['location' => 'campus']);
        Sanctum::actingAs($user);

        $ticket = Ticket::create([
            'key' => 'N-0003',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
            'called_at' => Carbon::now(),
        ]);

        Event::fake([TicketsUpdated::class]);

        $this->withHeader('X-API-KEY', 'test-api-key')
            ->patchJson("/api/tickets/{$ticket->id}/cancel");

        Event::assertDispatched(TicketsUpdated::class, fn (TicketsUpdated $event) => $event->location === 'campus');
    }
}
