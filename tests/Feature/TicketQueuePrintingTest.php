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

    /**
     * Test that creating a ticket dispatches PrintTicketJob to queue
     */
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

        // Verify PrintTicketJob was dispatched at least once
        Queue::assertPushed(PrintTicketJob::class);

        // Verify ticket was actually created in database
        $ticket = Ticket::where('key', $response->json('ticket.key'))->first();
        $this->assertNotNull($ticket);
        $this->assertEquals($response->json('ticket.key'), $ticket->key);
        $this->assertEquals('campus', $ticket->location);
        $this->assertFalse($ticket->completed);
    }

    /**
     * Test that job is queued on 'printing' queue
     */
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

    /**
     * Test that multiple tickets generate multiple print jobs
     */
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
