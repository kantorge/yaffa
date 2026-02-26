<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class CategoryApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_active_status_of_a_category(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        $this->actingAs($user);
        $response = $this->put(route('api.category.updateActive', [
            'category' => $category->id,
            'active' => true,
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $this->assertTrue($category->fresh()->active);
    }

    public function test_it_throws_an_authorization_exception_if_user_is_not_authorized_to_update_a_category(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        // Create a different user
        /** @var User $user2 */
        $user2 = User::factory()->create();

        // Try to update the category as an unauthenticated user
        $response = $this->put(
            route('api.category.updateActive', [
                'category' => $category->id,
                'active' => true,
            ]),
            [],
            [
                'Accept' => 'application/json'
            ]
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertFalse($category->fresh()->active);

        // Try to update the category as the different user
        $this->actingAs($user2);
        $response = $this->put(route('api.category.updateActive', [
            'category' => $category->id,
            'active' => true,
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertFalse($category->fresh()->active);
    }

    public function test_it_deletes_a_category(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()
            ->for($user)
            ->create();

        $this->actingAs($user);
        $response = $this->delete(route('api.category.destroy', [
            'category' => $category->id,
        ]));

        $response->assertStatus(Response::HTTP_OK);
        // The response should contain the deleted category
        $response->assertJsonFragment([
            'id' => $category->id,
        ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_it_does_not_delete_a_category_with_children(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()
            ->for($user)
            ->create();

        // Create a children for this category
        Category::factory()
            ->for($user)
            ->create([
                'parent_id' => $category->id,
            ]);

        $this->actingAs($user);
        $response = $this->delete(route('api.category.destroy', [
            'category' => $category->id,
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'error' => __('Category is in use, cannot be deleted'),
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_it_creates_a_category(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Test Category',
            'active' => true,
            'default_aggregation' => 'month',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment([
            'name' => 'Test Category',
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'user_id' => $user->id,
        ]);
    }

    public function test_it_creates_a_category_with_parent(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $parentCategory */
        $parentCategory = Category::factory()
            ->for($user)
            ->create();

        $this->actingAs($user);
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Child Category',
            'active' => true,
            'parent_id' => $parentCategory->id,
            'default_aggregation' => 'month',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment([
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_it_rejects_creating_a_category_without_required_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        // Missing name
        $response = $this->postJson(route('api.category.store'), [
            'default_aggregation' => 'month',
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Missing default_aggregation
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Test',
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_it_rejects_creating_a_category_with_invalid_parent(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Test Category',
            'active' => true,
            'parent_id' => 99999,
            'default_aggregation' => 'month',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_it_rejects_creating_a_duplicate_category(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Category::factory()
            ->for($user)
            ->create(['name' => 'Existing Category']);

        $this->actingAs($user);
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Existing Category',
            'active' => true,
            'default_aggregation' => 'month',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_it_rejects_creating_a_category_with_parent_from_another_user(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();

        /** @var User $user2 */
        $user2 = User::factory()->create();

        /** @var Category $parentCategory */
        $parentCategory = Category::factory()
            ->for($user1)
            ->create();

        $this->actingAs($user2);
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Child Category',
            'active' => true,
            'parent_id' => $parentCategory->id,
            'default_aggregation' => 'month',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('parent_id');
    }

    public function test_different_users_can_create_categories_with_the_same_name(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();

        /** @var User $user2 */
        $user2 = User::factory()->create();

        Category::factory()
            ->for($user1)
            ->create(['name' => 'Groceries']);

        $this->actingAs($user2);
        $response = $this->postJson(route('api.category.store'), [
            'name' => 'Groceries',
            'active' => true,
            'default_aggregation' => 'month',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('categories', [
            'name' => 'Groceries',
            'user_id' => $user2->id,
        ]);
    }

    public function test_it_does_not_delete_a_category_if_it_is_used_in_a_transaction(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        $categoryParent = Category::factory()
            ->for($user)
            ->create();
        $categoryChild = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => $categoryParent->id,
            ]);

        // Create a transaction for this category, which also needs other models:
        // account group, currency, account, payee
        AccountGroup::factory()
            ->for($user)
            ->create();

        Currency::factory()
            ->for($user)
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        // Create a standard transaction with specific data
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal($user)
            ->create();

        TransactionItem::factory()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryChild->id,
        ]);

        $this->actingAs($user);
        $response = $this->delete(route('api.category.destroy', [
            'category' => $categoryChild->id,
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'error' => __('Category is in use, cannot be deleted'),
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryChild->id,
        ]);
    }
}
