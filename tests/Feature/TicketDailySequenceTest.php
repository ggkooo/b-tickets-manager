<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketDailySequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resets_ticket_sequence_per_prefix_each_day(): void
    {
        config(['app.api_key' => 'test-api-key']);

        DB::table('tickets')->insert([
            'key' => 'N-0001',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
            'created_at' => Carbon::parse('2026-04-05 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-05 10:00:00'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-04-06 08:00:00'));

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Atendimento Normal',
                'location' => 'campus',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('ticket.key', 'N-0001');

        Carbon::setTestNow();
    }

    public function test_it_continues_incrementing_within_the_same_day(): void
    {
        config(['app.api_key' => 'test-api-key']);

        Carbon::setTestNow(Carbon::parse('2026-04-06 09:00:00'));

        Ticket::create([
            'key' => 'N-0001',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => false,
            'created_at' => Carbon::now()->subMinute(),
            'updated_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Atendimento Normal',
                'location' => 'campus',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('ticket.key', 'N-0002');

        Carbon::setTestNow();
    }
}
