<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketRecallTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recalls_an_already_called_ticket(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $user = User::factory()->create([
            'name' => 'Guiche 01',
        ]);

        Sanctum::actingAs($user);

        $ticket = Ticket::create([
            'key' => 'N-0001',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
            'called_at' => Carbon::now()->subMinute(),
            'guiche' => 'Guiche 00',
        ]);

        Carbon::setTestNow(Carbon::now()->addMinute());

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson("/api/tickets/{$ticket->id}/recall");

        $response
            ->assertOk()
            ->assertJsonPath('id', $ticket->id)
            ->assertJsonPath('guiche', 'Guiche 01')
            ->assertJsonPath('completed', false);

        $this->assertEquals('Guiche 01', $ticket->fresh()->guiche);
        $this->assertEquals(
            Carbon::now()->toDateTimeString(),
            $ticket->fresh()->called_at?->toDateTimeString()
        );

        Carbon::setTestNow();
    }

    public function test_it_rejects_recall_when_ticket_was_not_called_yet(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $user = User::factory()->create([
            'name' => 'Guiche 01',
        ]);

        Sanctum::actingAs($user);

        $ticket = Ticket::create([
            'key' => 'N-0002',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
            'called_at' => null,
        ]);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson("/api/tickets/{$ticket->id}/recall");

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este ticket ainda nao foi chamado.');
    }
}
