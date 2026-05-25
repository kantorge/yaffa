<?php

namespace Tests\Feature\API;

use App\Models\Category;
use App\Models\CategoryLearning;
use App\Models\User;
use App\Services\CategoryLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CategoryLearningApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_active_learnings_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $activeCategory = Category::factory()->create(['user_id' => $user->id]);
        $inactiveCategory = Category::factory()->create(['user_id' => $user->id]);
        $otherCategory = Category::factory()->create(['user_id' => $otherUser->id]);

        CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $activeCategory->id,
            'usage_count' => 4,
            'active' => true,
        ]);

        CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'tea',
            'category_id' => $inactiveCategory->id,
            'usage_count' => 2,
            'active' => false,
        ]);

        CategoryLearning::factory()->create([
            'user_id' => $otherUser->id,
            'item_description' => 'coffee',
            'category_id' => $otherCategory->id,
            'usage_count' => 9,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.category-learning.index'));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.item_description', 'coffee')
            ->assertJsonPath('0.status', 'active');
    }

    public function test_show_returns_single_learning_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $learning = CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'coffee',
            'category_id' => $category->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.category-learning.show', ['categoryLearning' => $learning->id]))
            ->assertOk()
            ->assertJsonPath('id', $learning->id)
            ->assertJsonPath('item_description', 'coffee');
    }

    public function test_store_deactivate_activate_and_delete_learning(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $createResponse = $this->postJson(route('api.v1.category-learning.store'), [
            'item_description' => 'Coffee Beans',
            'category_id' => $category->id,
            'active' => true,
        ]);

        $createResponse->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('item_description', 'coffee beans')
            ->assertJsonPath('usage_count', 0)
            ->assertJsonPath('status', 'active');

        $learningId = (int) $createResponse->json('id');

        $this->assertDatabaseHas('category_learning', [
            'id' => $learningId,
            'user_id' => $user->id,
            'item_description' => 'coffee beans',
            'category_id' => $category->id,
            'usage_count' => 0,
        ]);

        $archiveResponse = $this->postJson(route('api.v1.category-learning.deactivate', ['categoryLearning' => $learningId]));

        $archiveResponse->assertOk()->assertJsonPath('status', 'inactive');

        $this->assertDatabaseHas('category_learning', [
            'id' => $learningId,
            'user_id' => $user->id,
        ]);

        $this->assertFalse(CategoryLearning::query()->findOrFail($learningId)->active);

        $unarchiveResponse = $this->postJson(route('api.v1.category-learning.activate', ['categoryLearning' => $learningId]));

        $unarchiveResponse->assertOk()->assertJsonPath('status', 'active');

        $this->assertTrue(CategoryLearning::query()->findOrFail($learningId)->active);

        $deleteResponse = $this->deleteJson(route('api.v1.category-learning.destroy', ['categoryLearning' => $learningId]));

        $deleteResponse->assertNoContent();
        $this->assertDatabaseMissing('category_learning', ['id' => $learningId]);
    }

    public function test_store_activates_inactive_learning_instead_of_creating_duplicate(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $service = new CategoryLearningService($user);

        $learning = CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => $service->normalize('Coffee Beans'),
            'category_id' => $category->id,
            'usage_count' => 7,
            'active' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.v1.category-learning.store'), [
            'item_description' => 'Coffee Beans',
            'category_id' => $category->id,
            'active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('id', $learning->id)
            ->assertJsonPath('status', 'active');

        $learning->refresh();
        $this->assertTrue($learning->active);
        $this->assertSame(7, $learning->usage_count);
    }

    public function test_cannot_manage_other_users_learning(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $otherUser->id]);

        $learning = CategoryLearning::factory()->create([
            'user_id' => $otherUser->id,
            'item_description' => 'coffee',
            'category_id' => $category->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.category-learning.deactivate', ['categoryLearning' => $learning->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->deleteJson(route('api.v1.category-learning.destroy', ['categoryLearning' => $learning->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_merge_combines_usage_and_deletes_source(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $source = CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'coffee beans',
            'category_id' => $category->id,
            'usage_count' => 4,
            'active' => true,
        ]);

        $target = CategoryLearning::factory()->create([
            'user_id' => $user->id,
            'item_description' => 'coffee purchase',
            'category_id' => $category->id,
            'usage_count' => 6,
            'active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.category-learning.merge'), [
            'source_id' => $source->id,
            'target_id' => $target->id,
        ])
            ->assertOk()
            ->assertJsonPath('id', $target->id)
            ->assertJsonPath('usage_count', 10);

        $this->assertDatabaseMissing('category_learning', ['id' => $source->id]);
        $this->assertDatabaseHas('category_learning', [
            'id' => $target->id,
            'usage_count' => 10,
        ]);
    }
}
