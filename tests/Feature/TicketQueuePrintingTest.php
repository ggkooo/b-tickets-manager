<?php

namespace Tests\Feature;

use App\Jobs\PrintTicketJob;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketQueuePrintingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_ticket_dispatches_print_job_to_queue(): void
    {
        Queue::fake();
        config(['app.api_key' => 'test-api-key']);

        $user = User::factory()
            ->create([
                'location' => 'campus',
                'active' => true,
            ]);

        Sanctum::actingAs($user);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Atendimento Normal',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'ticket' => [
                'id',
                'key',
                'location',
                'service_type',
            ],
            'print' => [
                'status',
            ],
        ]);

        $this->assertEquals('enviando', $response->json('print.status'));

        Queue::assertPushed(PrintTicketJob::class);

        $ticket = Ticket::where('key', $response->json('ticket.key'))->first();
        $this->assertNotNull($ticket);
        $this->assertEquals($response->json('ticket.key'), $ticket->key);
        $this->assertEquals('campus', $ticket->location);
        $this->assertFalse($ticket->completed);
    }

    public function test_print_job_uses_printing_queue(): void
    {
        Queue::fake();
        config(['app.api_key' => 'test-api-key']);

        $user = User::factory()
            ->create([
                'location' => 'centro',
                'active' => true,
            ]);

        Sanctum::actingAs($user);

        $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Atendimento Preferencial',
            ]);

        Queue::assertPushedOn('printing', PrintTicketJob::class);
    }

    public function test_multiple_tickets_generate_multiple_print_jobs(): void
    {
        Queue::fake();
        config(['app.api_key' => 'test-api-key']);

        $user = User::factory()
            ->create([
                'location' => 'campus',
                'active' => true,
            ]);

        Sanctum::actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this
                ->withHeader('X-API-KEY', 'test-api-key')
                ->postJson('/api/tickets', [
                    'service_type' => 'Atendimento Normal',
                ]);
        }

        Queue::assertPushedTimes(PrintTicketJob::class, 3);
    }
}
