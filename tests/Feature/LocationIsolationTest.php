<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(): array
    {
        return [
            'X-API-KEY' => 'test-api-key',
            'Accept' => 'application/json',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.api_key', 'test-api-key');
        putenv('APP_API_KEY=test-api-key');
    }

    public function test_ticket_listing_is_scoped_by_location(): void
    {
        Ticket::create([
            'key' => 'N-0001',
            'location' => User::LOCATION_CAMPUS,
            'service_type' => 'Atendimento Normal',
            'completed' => false,
        ]);

        Ticket::create([
            'key' => 'N-0001',
            'location' => User::LOCATION_CENTRO,
            'service_type' => 'Atendimento Normal',
            'completed' => false,
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/tickets?location=campus');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.location', User::LOCATION_CAMPUS)
            ->assertJsonPath('0.key', 'N-0001');
    }

    public function test_login_uses_location_scope_for_same_login(): void
    {
        $campusUser = User::factory()->create([
            'login' => 'operador',
            'location' => User::LOCATION_CAMPUS,
            'password' => bcrypt('campus-secret'),
        ]);

        $centroUser = User::factory()->create([
            'login' => 'operador',
            'location' => User::LOCATION_CENTRO,
            'password' => bcrypt('centro-secret'),
        ]);

        $campusLogin = $this->withHeaders($this->apiHeaders())->postJson('/api/login', [
            'login' => 'operador',
            'password' => 'campus-secret',
            'location' => User::LOCATION_CAMPUS,
        ]);

        $campusLogin
            ->assertOk()
            ->assertJsonPath('data.user.id', $campusUser->id)
            ->assertJsonPath('data.user.location', User::LOCATION_CAMPUS);

        $centroLogin = $this->withHeaders($this->apiHeaders())->postJson('/api/login', [
            'login' => 'operador',
            'password' => 'centro-secret',
            'location' => User::LOCATION_CENTRO,
        ]);

        $centroLogin
            ->assertOk()
            ->assertJsonPath('data.user.id', $centroUser->id)
            ->assertJsonPath('data.user.location', User::LOCATION_CENTRO);
    }

    public function test_superadmin_cannot_manage_user_from_another_location(): void
    {
        $campusAdmin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $campusAdmin->createToken('test-token')->plainTextToken;

        $centroUser = User::factory()->create([
            'location' => User::LOCATION_CENTRO,
        ]);

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/users/' . $centroUser->id, [
            'name' => 'Nao Deve Atualizar',
        ]);

        $response->assertNotFound();
    }
}
