<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrinterSettingsManagementTest extends TestCase
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

    public function test_superadmin_can_save_network_printer_settings_for_own_location(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'enabled' => true,
                'connection_type' => 'network',
                'host' => '10.0.0.25',
                'port' => 9100,
                'profile' => 'simple',
                'header' => 'SENHA CAMPUS',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.location', User::LOCATION_CAMPUS)
            ->assertJsonPath('data.connection_type', 'network')
            ->assertJsonPath('data.host', '10.0.0.25')
            ->assertJsonPath('data.port', 9100)
            ->assertJsonPath('data.share_path', null);

        $this->assertDatabaseHas('printer_settings', [
            'location' => User::LOCATION_CAMPUS,
            'connection_type' => 'network',
            'host' => '10.0.0.25',
            'port' => 9100,
        ]);
    }

    public function test_superadmin_can_save_shared_windows_printer_settings_for_own_location(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CENTRO,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'enabled' => true,
                'connection_type' => 'shared_windows',
                'share_path' => '\\\\PC-CENTRO\\EPSON-TM-T20',
                'profile' => 'simple',
                'header' => 'SENHA CENTRO',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.location', User::LOCATION_CENTRO)
            ->assertJsonPath('data.connection_type', 'shared_windows')
            ->assertJsonPath('data.share_path', '\\\\PC-CENTRO\\EPSON-TM-T20')
            ->assertJsonPath('data.host', null)
            ->assertJsonPath('data.port', null);

        $this->assertDatabaseHas('printer_settings', [
            'location' => User::LOCATION_CENTRO,
            'connection_type' => 'shared_windows',
            'share_path' => '\\\\PC-CENTRO\\EPSON-TM-T20',
        ]);
    }

    public function test_admin_cannot_manage_printer_settings(): void
    {
        $user = User::factory()->admin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'enabled' => true,
                'connection_type' => 'network',
                'host' => '10.0.0.25',
                'port' => 9100,
            ]);

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden: super administrator access required',
            ]);
    }

    public function test_network_mode_requires_host(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'enabled' => true,
                'connection_type' => 'network',
                'port' => 9100,
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Host e obrigatorio para impressora de rede.',
            ]);
    }

    public function test_shared_windows_mode_requires_share_path(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CENTRO,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'enabled' => true,
                'connection_type' => 'shared_windows',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'share_path e obrigatorio para impressora compartilhada no Windows.',
            ]);
    }
}
