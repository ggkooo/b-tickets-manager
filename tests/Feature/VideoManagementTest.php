<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoManagementTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(string $token = ''): array
    {
        return array_filter([
            'X-API-KEY' => 'test-api-key',
            'Accept' => 'application/json',
            'Authorization' => $token ? 'Bearer ' . $token : null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.api_key', 'test-api-key');
        putenv('APP_API_KEY=test-api-key');
        Storage::fake('public');
    }

    public function test_superadmin_can_upload_a_video_for_own_location(): void
    {
        $admin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->post('/api/videos/upload', [
                'video' => UploadedFile::fake()->create('promo.mp4', 100, 'video/mp4'),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', Video::TYPE_UPLOAD);

        $this->assertDatabaseHas('videos', [
            'location' => User::LOCATION_CAMPUS,
            'type' => Video::TYPE_UPLOAD,
        ]);
    }

    public function test_superadmin_can_add_a_video_link_for_own_location(): void
    {
        $admin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CENTRO]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/videos/link', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', Video::TYPE_LINK)
            ->assertJsonPath('data.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertDatabaseHas('videos', [
            'location' => User::LOCATION_CENTRO,
            'type' => Video::TYPE_LINK,
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_superadmin_can_upload_a_quicktime_branded_mp4(): void
    {
        // Files saved with a .mp4 extension (common from Apple devices and
        // some editors) are sometimes detected as video/quicktime rather
        // than video/mp4 — these must still be accepted.
        $admin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->post('/api/videos/upload', [
                'video' => UploadedFile::fake()->create('promo.mp4', 100, 'video/quicktime'),
            ]);

        $response->assertCreated();
    }

    public function test_video_link_must_be_a_valid_url(): void
    {
        $admin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/videos/link', [
                'url' => 'not-a-url',
            ]);

        $response->assertStatus(422);
    }

    public function test_public_video_listing_is_scoped_to_the_requested_location_only(): void
    {
        Video::factory()->create(['location' => User::LOCATION_CAMPUS, 'type' => Video::TYPE_LINK, 'url' => 'https://youtube.com/watch?v=campus']);
        Video::factory()->create(['location' => User::LOCATION_CENTRO, 'type' => Video::TYPE_LINK, 'url' => 'https://youtube.com/watch?v=centro']);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/videos?location=campus');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.url', 'https://youtube.com/watch?v=campus');
    }

    public function test_authenticated_admin_can_list_videos_without_a_location_query_param(): void
    {
        Video::factory()->create(['location' => User::LOCATION_CAMPUS, 'type' => Video::TYPE_LINK, 'url' => 'https://youtube.com/watch?v=campus']);
        Video::factory()->create(['location' => User::LOCATION_CENTRO, 'type' => Video::TYPE_LINK, 'url' => 'https://youtube.com/watch?v=centro']);

        $admin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/videos');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.url', 'https://youtube.com/watch?v=campus');
    }

    public function test_superadmin_cannot_delete_a_video_from_another_location(): void
    {
        $centroVideo = Video::factory()->create(['location' => User::LOCATION_CENTRO, 'type' => Video::TYPE_LINK, 'url' => 'https://youtube.com/watch?v=x']);

        $campusAdmin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $campusAdmin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->deleteJson('/api/videos/' . $centroVideo->id);

        $response->assertStatus(404);
        $this->assertDatabaseHas('videos', ['id' => $centroVideo->id]);
    }

    public function test_superadmin_can_delete_own_uploaded_video_and_its_file(): void
    {
        $admin = User::factory()->superAdmin()->create(['location' => User::LOCATION_CAMPUS]);
        $token = $admin->createToken('test-token')->plainTextToken;

        Storage::disk('public')->put('videos/keep-this-file.mp4', 'fake-content');
        $video = Video::factory()->create([
            'location' => User::LOCATION_CAMPUS,
            'type' => Video::TYPE_UPLOAD,
            'filename' => 'keep-this-file.mp4',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->deleteJson('/api/videos/' . $video->id);

        $response->assertOk();
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
        Storage::disk('public')->assertMissing('videos/keep-this-file.mp4');
    }

    public function test_regular_admin_cannot_manage_videos(): void
    {
        $admin = User::factory()->create(['location' => User::LOCATION_CAMPUS, 'is_admin' => true]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/videos/link', ['url' => 'https://youtube.com/watch?v=x']);

        $response->assertStatus(403);
    }
}
