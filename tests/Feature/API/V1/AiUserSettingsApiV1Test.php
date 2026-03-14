<?php

namespace Tests\Feature\API\V1;

use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUserSettingsApiV1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_unauthenticated_cannot_access_v1_show(): void
    {
        $response = $this->getJson(route('api.v1.ai.settings.show'));

        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_unauthenticated_cannot_access_v1_update(): void
    {
        $response = $this->patchJson(route('api.v1.ai.settings.update'), [
            'ai_enabled' => true,
        ]);

        $this->assertUserNotAuthorized($response);
        $response->assertJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_v1_show_returns_resolved_settings_for_authenticated_user(): void
    {
        AiUserSettings::factory()->create([
            'user_id' => $this->user->id,
            'ai_enabled' => true,
            'ocr_language' => 'fra',
            'category_matching_mode' => 'parent_only',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.settings.show'));

        $response->assertOk()
            ->assertJsonStructure([
                'ai_enabled',
                'ocr_language',
                'image_max_width_vision',
                'image_max_height_vision',
                'image_quality_vision',
                'image_max_width_tesseract',
                'image_max_height_tesseract',
                'asset_similarity_threshold',
                'asset_max_suggestions',
                'match_auto_accept_threshold',
                'duplicate_date_window_days',
                'duplicate_amount_tolerance_percent',
                'duplicate_similarity_threshold',
                'category_matching_mode',
                'warnings',
            ])
            ->assertJson([
                'ai_enabled' => true,
                'ocr_language' => 'fra',
                'category_matching_mode' => 'parent_only',
            ]);
    }

    public function test_v1_show_creates_missing_settings_row_and_returns_defaults(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.settings.show'));

        $response->assertOk()
            ->assertJsonPath('ocr_language', 'eng')
            ->assertJsonPath('ai_enabled', false);

        $this->assertDatabaseHas('ai_user_settings', [
            'user_id' => $this->user->id,
            'ocr_language' => 'eng',
        ]);
    }

    public function test_v1_show_does_not_expose_other_users_settings(): void
    {
        AiUserSettings::factory()->create([
            'user_id' => $this->otherUser->id,
            'ocr_language' => 'deu',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.settings.show'));

        $response->assertOk()
            ->assertJsonPath('ocr_language', 'eng');
    }

    public function test_v1_show_includes_warning_for_child_mode_without_active_child_categories(): void
    {
        AiUserSettings::factory()->create([
            'user_id' => $this->user->id,
            'category_matching_mode' => 'child_preferred',
        ]);

        Category::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.v1.ai.settings.show'));

        $response->assertOk()
            ->assertJsonPath('warnings.0.code', 'NO_ACTIVE_CHILD_CATEGORIES');
    }

    public function test_v1_update_persists_partial_settings_changes(): void
    {
        AiUserSettings::factory()->create([
            'user_id' => $this->user->id,
            'ai_enabled' => false,
            'ocr_language' => 'eng',
            'category_matching_mode' => 'child_preferred',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.ai.settings.update'), [
                'ai_enabled' => true,
                'ocr_language' => 'hun',
                'category_matching_mode' => 'best_match',
                'match_auto_accept_threshold' => 0.9,
            ]);

        $response->assertOk()
            ->assertJson([
                'ai_enabled' => true,
                'ocr_language' => 'hun',
                'category_matching_mode' => 'best_match',
                'match_auto_accept_threshold' => 0.9,
            ]);

        $this->assertDatabaseHas('ai_user_settings', [
            'user_id' => $this->user->id,
            'ai_enabled' => true,
            'ocr_language' => 'hun',
            'category_matching_mode' => 'best_match',
        ]);
    }

    public function test_v1_update_creates_missing_row_before_persisting(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.ai.settings.update'), [
                'ai_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('ai_enabled', true);

        $this->assertDatabaseHas('ai_user_settings', [
            'user_id' => $this->user->id,
            'ai_enabled' => true,
        ]);
    }

    public function test_v1_update_validation_uses_default_validation_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.ai.settings.update'), [
                'asset_similarity_threshold' => 1.5,
                'duplicate_amount_tolerance_percent' => 150,
                'category_matching_mode' => 'invalid-mode',
            ]);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'asset_similarity_threshold',
                    'duplicate_amount_tolerance_percent',
                    'category_matching_mode',
                ],
            ]);
    }

    public function test_v1_update_rejects_invalid_numeric_ranges(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson(route('api.v1.ai.settings.update'), [
                'image_quality_vision' => 101,
                'image_max_width_tesseract' => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image_quality_vision', 'image_max_width_tesseract']);
    }
}
