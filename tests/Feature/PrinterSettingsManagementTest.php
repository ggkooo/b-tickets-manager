<?php

namespace Tests\Feature;

use App\Models\PrinterSetting;
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

    public function test_superadmin_can_create_network_printer_settings_for_own_location(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'name' => 'Balcao 1',
                'enabled' => true,
                'connection_type' => 'network',
                'host' => '10.0.0.25',
                'port' => 9100,
                'profile' => 'simple',
                'header' => 'SENHA CAMPUS',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.location', User::LOCATION_CAMPUS)
            ->assertJsonPath('data.name', 'Balcao 1')
            ->assertJsonPath('data.connection_type', 'network')
            ->assertJsonPath('data.host', '10.0.0.25')
            ->assertJsonPath('data.port', 9100)
            ->assertJsonPath('data.share_path', null);

        $this->assertDatabaseHas('printer_settings', [
            'location' => User::LOCATION_CAMPUS,
            'name' => 'Balcao 1',
            'connection_type' => 'network',
            'host' => '10.0.0.25',
            'port' => 9100,
        ]);
    }

    public function test_superadmin_can_create_multiple_printer_settings_for_same_location(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CENTRO,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'name' => 'Recepcao',
                'enabled' => true,
                'connection_type' => 'shared_windows',
                'share_path' => '\\\\PC-CENTRO\\EPSON-TM-T20',
                'profile' => 'simple',
                'header' => 'SENHA CENTRO',
            ])
            ->assertCreated();

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'name' => 'Triagem',
                'enabled' => false,
                'connection_type' => 'network',
                'host' => '10.0.0.30',
                'port' => 9100,
                'profile' => 'simple',
                'header' => 'SENHA CENTRO 2',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.location', User::LOCATION_CENTRO)
            ->assertJsonPath('data.name', 'Triagem')
            ->assertJsonPath('data.connection_type', 'network')
            ->assertJsonPath('data.host', '10.0.0.30')
            ->assertJsonPath('data.port', 9100);

        $listResponse = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/printer-settings');

        $listResponse
            ->assertOk()
            ->assertJsonPath('location', User::LOCATION_CENTRO)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('printer_settings', [
            'location' => User::LOCATION_CENTRO,
            'name' => 'Recepcao',
            'connection_type' => 'shared_windows',
            'share_path' => '\\\\PC-CENTRO\\EPSON-TM-T20',
        ]);

        $this->assertDatabaseHas('printer_settings', [
            'location' => User::LOCATION_CENTRO,
            'name' => 'Triagem',
            'connection_type' => 'network',
            'host' => '10.0.0.30',
        ]);
    }

    public function test_superadmin_can_update_and_disable_a_printer_setting(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $printerSetting = PrinterSetting::query()->create([
            'location' => User::LOCATION_CAMPUS,
            'name' => 'Balcao 1',
            'enabled' => true,
            'connection_type' => 'network',
            'host' => '10.0.0.25',
            'port' => 9100,
            'profile' => 'simple',
            'header' => 'SENHA CAMPUS',
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->patchJson('/api/printer-settings/' . $printerSetting->id, [
                'enabled' => false,
                'name' => 'Balcao Principal',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.name', 'Balcao Principal');

        $this->assertDatabaseHas('printer_settings', [
            'id' => $printerSetting->id,
            'location' => User::LOCATION_CAMPUS,
            'name' => 'Balcao Principal',
            'enabled' => false,
        ]);
    }

    public function test_printer_settings_listing_is_limited_to_authenticated_user_location(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        PrinterSetting::query()->create([
            'location' => User::LOCATION_CAMPUS,
            'name' => 'Campus 1',
            'enabled' => true,
            'connection_type' => 'network',
            'host' => '10.0.0.10',
            'port' => 9100,
            'profile' => 'simple',
            'header' => 'Campus',
        ]);

        PrinterSetting::query()->create([
            'location' => User::LOCATION_CENTRO,
            'name' => 'Centro 1',
            'enabled' => true,
            'connection_type' => 'shared_windows',
            'share_path' => '\\\\PC-CENTRO\\EPSON-TM-T20',
            'profile' => 'simple',
            'header' => 'Centro',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/printer-settings');

        $response
            ->assertOk()
            ->assertJsonPath('location', User::LOCATION_CAMPUS)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Campus 1');
    }

    public function test_admin_cannot_manage_printer_settings(): void
    {
        $user = User::factory()->admin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'name' => 'Balcao 1',
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
                'name' => 'Balcao 1',
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
                'name' => 'Recepcao',
                'enabled' => true,
                'connection_type' => 'shared_windows',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'share_path e obrigatorio para impressora compartilhada no Windows.',
            ]);
    }

    public function test_printer_name_must_be_unique_per_location(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'location' => User::LOCATION_CAMPUS,
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        PrinterSetting::query()->create([
            'location' => User::LOCATION_CAMPUS,
            'name' => 'Balcao 1',
            'enabled' => true,
            'connection_type' => 'network',
            'host' => '10.0.0.25',
            'port' => 9100,
            'profile' => 'simple',
            'header' => 'Campus',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/printer-settings', [
                'name' => 'Balcao 1',
                'enabled' => false,
                'connection_type' => 'network',
                'host' => '10.0.0.30',
                'port' => 9100,
            ]);

        $response->assertStatus(422);
    }
}
