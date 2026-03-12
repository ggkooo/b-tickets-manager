<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
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

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Zulu Admin',
        ]);
        $firstUser = User::factory()->create([
            'name' => 'Alice User',
            'login' => 'alice.user',
        ]);
        $secondUser = User::factory()->create([
            'name' => 'Bob User',
            'login' => 'bob.user',
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.name', 'Administrador')
            ->assertJsonPath('1.name', 'Alice User')
            ->assertJsonPath('2.name', 'Bob User')
            ->assertJsonPath('3.name', 'Zulu Admin');

        $response->assertJsonFragment([
            'login' => $firstUser->login,
            'is_admin' => false,
        ]);

        $response->assertJsonFragment([
            'login' => $admin->login,
            'is_admin' => true,
        ]);
    }

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create([
            'login' => 'regular.user',
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/users/' . $targetUser->id, [
            'name' => 'Updated User',
            'login' => 'updated.user',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
            'active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.login', 'updated.user')
            ->assertJsonPath('user.active', false);

        $targetUser->refresh();

        $this->assertSame('Updated User', $targetUser->name);
        $this->assertSame('updated.user', $targetUser->login);
        $this->assertFalse($targetUser->active);
        $this->assertTrue(Hash::check('newsecret123', $targetUser->password));
    }

    public function test_admin_can_promote_a_user_to_administrator(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/users/' . $targetUser->id . '/make-admin');

        $response
            ->assertOk()
            ->assertJsonPath('user.is_admin', true);

        $this->assertTrue($targetUser->fresh()->is_admin);
    }

    public function test_admin_can_remove_administrator_access_from_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->admin()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/users/' . $targetUser->id . '/remove-admin');

        $response
            ->assertOk()
            ->assertJsonPath('user.is_admin', false);

        $this->assertFalse($targetUser->fresh()->is_admin);
    }

    public function test_admin_can_delete_a_non_admin_user(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/users/' . $targetUser->id);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'User deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }

    public function test_non_admin_cannot_access_user_management_routes(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/users/' . $targetUser->id . '/make-admin');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden: administrator access required',
            ]);
    }

    public function test_cannot_delete_the_last_administrator(): void
    {
        $admin = User::where('login', 'admin')->firstOrFail();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/users/' . $admin->id);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete the last administrator',
            ]);
    }

    public function test_cannot_remove_admin_access_from_the_last_administrator(): void
    {
        $admin = User::where('login', 'admin')->firstOrFail();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            ...$this->apiHeaders(),
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/users/' . $admin->id . '/remove-admin');

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot remove administrator access from the last administrator',
            ]);
    }
}