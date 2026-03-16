<?php

namespace Tests\Unit\Services;

use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUserSettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    private AiUserSettingsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(AiUserSettingsResolver::class);
    }

    public function test_it_resolves_user_database_values_first(): void
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'ocr_language' => 'fra',
            'asset_similarity_threshold' => 0.777,
            'prompt_chat_history_enabled' => false,
            'category_matching_mode' => 'parent_only',
        ]);

        $resolved = $this->resolver->resolveForUser($user);

        $this->assertSame('fra', $resolved['ocr_language']);
        $this->assertSame(0.777, $resolved['asset_similarity_threshold']);
        $this->assertFalse($resolved['prompt_chat_history_enabled']);
        $this->assertSame('parent_only', $resolved['category_matching_mode']);
    }

    public function test_it_uses_hardcoded_defaults_when_creating_missing_settings(): void
    {
        $user = User::factory()->create();

        $parent = Category::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'parent_id' => null,
        ]);

        Category::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'parent_id' => $parent->id,
        ]);

        $resolved = $this->resolver->resolveForUser($user);

        $this->assertSame('eng', $resolved['ocr_language']);
        $this->assertSame(2048, $resolved['image_max_width_vision']);
        $this->assertSame(2048, $resolved['image_max_height_vision']);
        $this->assertSame(85, $resolved['image_quality_vision']);
        $this->assertSame(0.5, $resolved['asset_similarity_threshold']);
        $this->assertSame(10, $resolved['asset_max_suggestions']);
        $this->assertTrue($resolved['prompt_chat_history_enabled']);
        $this->assertSame(3, $resolved['duplicate_date_window_days']);
        $this->assertSame(10.0, $resolved['duplicate_amount_tolerance_percent']);
        $this->assertSame(0.5, $resolved['duplicate_similarity_threshold']);
        $this->assertSame([], $resolved['warnings']);
    }

    public function test_it_returns_warning_for_child_oriented_mode_without_active_child_categories(): void
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'category_matching_mode' => 'child_preferred',
        ]);

        Category::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'parent_id' => null,
        ]);

        $resolved = $this->resolver->resolveForUser($user);

        $this->assertCount(1, $resolved['warnings']);
        $this->assertSame('NO_ACTIVE_CHILD_CATEGORIES', $resolved['warnings'][0]['code']);
    }

    public function test_it_does_not_return_warning_when_active_child_category_exists(): void
    {
        $user = User::factory()->create();
        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'category_matching_mode' => 'child_only',
        ]);

        $parent = Category::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'parent_id' => null,
        ]);

        Category::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'parent_id' => $parent->id,
        ]);

        $resolved = $this->resolver->resolveForUser($user);

        $this->assertSame([], $resolved['warnings']);
    }

    public function test_it_updates_and_returns_persisted_settings(): void
    {
        $user = User::factory()->create();

        $settings = $this->resolver->updateForUser($user, [
            'ai_enabled' => true,
            'prompt_chat_history_enabled' => false,
            'ocr_language' => 'hun',
            'match_auto_accept_threshold' => 0.88,
            'category_matching_mode' => 'best_match',
        ]);

        $this->assertTrue($settings->ai_enabled);
        $this->assertFalse($settings->prompt_chat_history_enabled);
        $this->assertSame('hun', $settings->ocr_language);
        $this->assertSame(0.88, $settings->match_auto_accept_threshold);
        $this->assertSame('best_match', $settings->category_matching_mode);

        $this->assertDatabaseHas('ai_user_settings', [
            'user_id' => $user->id,
            'ai_enabled' => true,
            'prompt_chat_history_enabled' => false,
            'ocr_language' => 'hun',
            'category_matching_mode' => 'best_match',
        ]);
    }
}
