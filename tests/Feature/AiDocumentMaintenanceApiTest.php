<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiDocumentMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_trigger_old_file_cleanup(): void
    {
        $response = $this->postJson(route('api.v1.maintenance.cleanup-ai-document-old-files'));

        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_authenticated_user_can_queue_old_file_cleanup_for_current_user(): void
    {
        Artisan::spy();

        $user = User::factory()->create([
            'language' => 'en',
            'locale' => 'en-US',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.maintenance.cleanup-ai-document-old-files'));

        $response->assertOk()
            ->assertJsonPath('message', __('maintenance.aiDocumentOldFiles.queued'));

        Artisan::shouldHaveReceived('queue')
            ->once()
            ->with('ai-documents:cleanup-old-files', [
                'userId' => $user->id,
            ]);
    }
}
