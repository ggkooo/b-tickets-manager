<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_a_ticket_as_canceled_using_the_completion_fields(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $ticket = Ticket::create([
            'key' => 'N-0001',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
        ]);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->patchJson("/api/tickets/{$ticket->id}/cancel");

        $response
            ->assertOk()
            ->assertJsonPath('id', $ticket->id)
            ->assertJsonPath('completed', true)
            ->assertJsonPath('completed_at', fn ($value) => !empty($value));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'completed' => 1,
        ]);

        $this->assertNotNull($ticket->fresh()->completed_at);
    }
}
