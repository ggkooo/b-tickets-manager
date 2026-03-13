<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAttendancesOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(string $token): array
    {
        return [
            'X-API-KEY' => 'test-api-key',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.api_key', 'test-api-key');
        putenv('APP_API_KEY=test-api-key');
    }

    public function test_report_includes_attendances_by_outcome_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        Ticket::create([
            'key' => 'N-0001',
            'service_type' => 'Atendimento Normal',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 10:00:00'),
            'completion_type' => 'completed',
        ]);

        Ticket::create([
            'key' => 'P-0001',
            'service_type' => 'Atendimento Preferencial',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 11:00:00'),
            'completion_type' => 'canceled',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/reports/attendances?start_date=2026-03-12&end_date=2026-03-12');

        $response
            ->assertOk()
            ->assertJsonPath('total_attendances', 2)
            ->assertJsonPath('attendances_by_outcome.completed', 1)
            ->assertJsonPath('attendances_by_outcome.canceled', 1)
            ->assertJsonPath('attendances_by_outcome.unknown', 0);
    }
}
