<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
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

    public function test_migrations_create_a_default_admin_account(): void
    {
        $user = User::where('login', 'admin')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('admin', $user->password));
    }

    public function test_login_returns_admin_flag_for_admin_user(): void
    {
        $user = User::factory()->admin()->create([
            'login' => 'admin.user',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => $user->login,
            'password' => 'secret123',
            'location' => $user->location,
        ], $this->apiHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('data.user.login', $user->login)
            ->assertJsonPath('data.user.location', $user->location)
            ->assertJsonPath('data.user.is_admin', true)
            ->assertJsonPath('data.user.is_super_admin', false)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => ['id', 'uuid', 'name', 'login', 'location', 'active', 'is_admin', 'is_super_admin'],
                ],
            ]);
    }

    public function test_login_returns_superadmin_flag_for_superadmin_user(): void
    {
        $user = User::factory()->superAdmin()->create([
            'login' => 'super.admin',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => $user->login,
            'password' => 'secret123',
            'location' => $user->location,
        ], $this->apiHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('data.user.login', $user->login)
            ->assertJsonPath('data.user.is_admin', false)
            ->assertJsonPath('data.user.is_super_admin', true);
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'login' => 'deactivated.user',
            'password' => bcrypt('secret123'),
            'active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'login' => $user->login,
            'password' => 'secret123',
            'location' => $user->location,
        ], $this->apiHeaders());

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_non_admin_user_cannot_access_admin_report_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/attendances?start_date=2026-03-01&end_date=2026-03-12');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden: administrator access required',
            ]);
    }

    public function test_admin_report_route_requires_authentication_and_returns_401_json(): void
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/reports/attendances?start_date=2026-03-01&end_date=2026-03-12');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_admin_user_can_access_admin_report_route(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/attendances?start_date=2026-03-01&end_date=2026-03-12');

        $response->assertOk();
    }

    public function test_superadmin_user_can_access_admin_report_route(): void
    {
        $user = User::factory()->superAdmin()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/attendances?start_date=2026-03-01&end_date=2026-03-12');

        $response->assertOk();
    }

    public function test_admin_cannot_access_superadmin_route(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden: super administrator access required',
            ]);
    }
}