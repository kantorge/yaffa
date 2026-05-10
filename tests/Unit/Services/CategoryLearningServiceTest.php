<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\CategoryLearning;
use App\Models\User;
use App\Services\CategoryLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    // ===== normalize() =====

    public function test_normalize_lowercases_and_trims_description(): void
    {
        $service = new CategoryLearningService();

        $this->assertSame('amazon marketplace', $service->normalize('  AMAZON MARKETPLACE  '));
    }

    public function test_normalize_removes_punctuation(): void
    {
        $service = new CategoryLearningService();

        $this->assertSame('coffee shop receipt', $service->normalize('Coffee Shop, Receipt!'));
    }

    public function test_normalize_collapses_whitespace(): void
    {
        $service = new CategoryLearningService();

        $this->assertSame('grocery store', $service->normalize("Grocery   Store"));
    }

    public function test_normalize_handles_unicode_characters(): void
    {
        $service = new CategoryLearningService();

        $normalized = $service->normalize('Café au lait');

        $this->assertSame('café au lait', $normalized);
    }

    public function test_normalize_produces_consistent_output_for_identical_inputs(): void
    {
        $service = new CategoryLearningService();

        $this->assertSame(
            $service->normalize('Lidl Supermarket'),
            $service->normalize('Lidl Supermarket')
        );
    }

    // ===== recordCategorySelection() =====

    public function test_record_creates_new_learning_entry(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $service = new CategoryLearningService($user);

        $service->recordCategorySelection('Coffee', $category->id);

        $this->assertDatabaseHas('category_learning', [
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $category->id,
            'usage_count' => 1,
        ]);
    }

    public function test_record_increments_existing_entry_when_same_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $service = new CategoryLearningService($user);

        CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $category->id,
            'usage_count' => 3,
        ]);

        $service->recordCategorySelection('Coffee', $category->id);

        $this->assertDatabaseHas('category_learning', [
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $category->id,
            'usage_count' => 4,
        ]);
    }

    public function test_record_resets_entry_when_different_category_selected(): void
    {
        $user = User::factory()->create();
        $oldCategory = Category::factory()->create(['user_id' => $user->id]);
        $newCategory = Category::factory()->create(['user_id' => $user->id]);
        $service = new CategoryLearningService($user);

        CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $oldCategory->id,
            'usage_count' => 5,
        ]);

        $service->recordCategorySelection('Coffee', $newCategory->id);

        $this->assertDatabaseHas('category_learning', [
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $newCategory->id,
            'usage_count' => 1,
        ]);
    }

    public function test_record_does_nothing_when_no_user_set(): void
    {
        $user = User::factory()->create();
        $service = new CategoryLearningService(null);
        $category = Category::factory()->create(['user_id' => $user->id]);

        $service->recordCategorySelection('Coffee', $category->id);

        $this->assertDatabaseMissing('category_learning', [
            'item_description' => 'coffee',
        ]);
    }

    public function test_record_normalizes_description_before_matching(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $service = new CategoryLearningService($user);

        CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'supermarket',
            'category_id' => $category->id,
            'usage_count' => 2,
        ]);

        // Different casing and whitespace, same normalized form
        $service->recordCategorySelection('  SUPERMARKET  ', $category->id);

        $this->assertDatabaseHas('category_learning', [
            'user_id' => $user->id,
            'item_description' => 'supermarket',
            'usage_count' => 3,
        ]);
    }
}
