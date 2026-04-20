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
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 10:00:00'),
            'completion_type' => 'completed',
        ]);

        Ticket::create([
            'key' => 'P-0001',
            'location' => 'campus',
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

    public function test_report_includes_attendances_grouped_by_guiche_and_all_users(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Guiche',
            'login' => 'admin.guiche',
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $otherUser = User::factory()->create([
            'name' => 'Sem Atendimento',
            'login' => 'sem.atendimento',
        ]);

        Ticket::create([
            'key' => 'N-0002',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => true,
            'guiche' => $admin->name,
            'attended_by_user_id' => $admin->id,
            'completed_at' => Carbon::parse('2026-03-12 10:00:00'),
            'completion_type' => 'completed',
        ]);

        Ticket::create([
            'key' => 'N-0003',
            'location' => 'campus',
            'service_type' => 'Atendimento Normal',
            'completed' => true,
            'guiche' => $admin->name,
            'attended_by_user_id' => $admin->id,
            'completed_at' => Carbon::parse('2026-03-12 11:00:00'),
            'completion_type' => 'canceled',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/reports/attendances?start_date=2026-03-12&end_date=2026-03-12');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'guiche' => 'Admin Guiche',
                'attended_by_user_id' => $admin->id,
                'attended_by_user_name' => 'Admin Guiche',
                'attended_by_user_login' => 'admin.guiche',
                'total' => 2,
                'completed' => 1,
                'canceled' => 1,
                'unknown' => 0,
            ])
            ->assertJsonFragment([
                'user_id' => $admin->id,
                'name' => 'Admin Guiche',
                'login' => 'admin.guiche',
                'guiche' => 'Admin Guiche',
                'total' => 2,
                'completed' => 1,
                'canceled' => 1,
                'unknown' => 0,
            ])
            ->assertJsonFragment([
                'user_id' => $otherUser->id,
                'name' => 'Sem Atendimento',
                'login' => 'sem.atendimento',
                'guiche' => 'Sem Atendimento',
                'total' => 0,
                'completed' => 0,
                'canceled' => 0,
                'unknown' => 0,
            ]);
    }
}
