<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAttendancesByTypeTest extends TestCase
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

    public function test_report_breaks_down_unilab_attendances_by_their_own_service_types(): void
    {
        $admin = User::factory()->admin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $admin->createToken('test-token')->plainTextToken;

        Ticket::create([
            'key' => 'N-0001',
            'location' => User::LOCATION_CAMPUS,
            'service_type' => 'Atendimento Normal',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 10:00:00'),
            'completion_type' => 'completed',
        ]);

        Ticket::create([
            'key' => 'P-0001',
            'location' => User::LOCATION_CAMPUS,
            'service_type' => 'Atendimento Preferencial',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 11:00:00'),
            'completion_type' => 'completed',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/reports/attendances?start_date=2026-03-12&end_date=2026-03-12');

        $response
            ->assertOk()
            ->assertJsonPath('attendances_by_type.Atendimento Normal', 1)
            ->assertJsonPath('attendances_by_type.Atendimento Preferencial', 1)
            ->assertJsonPath('attendances_by_type.Retirada de Exames ou Entrega de Amostras', 0)
            ->assertJsonMissingPath('attendances_by_type.priority')
            ->assertJsonMissingPath('attendances_by_type.others');
    }

    public function test_report_breaks_down_cre_attendances_by_their_own_service_types(): void
    {
        $admin = User::factory()->admin()->create(['location' => User::LOCATION_CRE_IJUI]);
        $token = $admin->createToken('test-token')->plainTextToken;

        Ticket::create([
            'key' => 'A-0001',
            'location' => User::LOCATION_CRE_IJUI,
            'service_type' => 'Acadêmico/Matrículas',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 10:00:00'),
            'completion_type' => 'completed',
        ]);

        Ticket::create([
            'key' => 'B-0001',
            'location' => User::LOCATION_CRE_IJUI,
            'service_type' => 'Impressão de Boletos',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 11:00:00'),
            'completion_type' => 'completed',
        ]);

        Ticket::create([
            'key' => 'B-0002',
            'location' => User::LOCATION_CRE_IJUI,
            'service_type' => 'Impressão de Boletos',
            'completed' => true,
            'completed_at' => Carbon::parse('2026-03-12 12:00:00'),
            'completion_type' => 'canceled',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/reports/attendances?start_date=2026-03-12&end_date=2026-03-12');

        $response
            ->assertOk()
            ->assertJsonPath('attendances_by_type.Acadêmico/Matrículas', 1)
            ->assertJsonPath('attendances_by_type.Solicitação de Documentos', 0)
            ->assertJsonPath('attendances_by_type.Impressão de Boletos', 2)
            ->assertJsonPath('attendances_by_type.Financiamentos e Bolsas', 0)
            ->assertJsonPath('attendances_by_type.Renegociação de Mensalidades', 0)
            ->assertJsonMissingPath('attendances_by_type.priority')
            ->assertJsonMissingPath('attendances_by_type.others');
    }
}
