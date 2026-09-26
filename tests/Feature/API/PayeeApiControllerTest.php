<?php

namespace Tests\Feature\API;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTestTransactions;
use Tests\TestCase;

class PayeeApiControllerTest extends TestCase
{
    use CreatesTestTransactions;
    use RefreshDatabase;

    private const BASE_PAYEE_NAME = 'Same payee name';

    public function test_cannot_accept_default_category_suggestion_with_other_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $payeeEntity = $this->createPayeeEntity($user, ['active' => true]);

        $foreignCategory = Category::factory()->for($otherUser)->create([
            'active' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.payees.category-suggestions.accept', [
            'accountEntity' => $payeeEntity->id,
            'category' => $foreignCategory->id,
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_payee_list_with_query_applies_all_provided_filters(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // One payee with base name
        AccountEntity::factory()->asPayee($user)->create([
            'active' => true,
            'name' => self::BASE_PAYEE_NAME,
        ]);

        // Other user with dummy data and same payee name
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createForUser($user2, AccountGroup::class);
        $this->createForUser($user2, Currency::class);

        AccountEntity::factory()->asPayee($user2)->create([
            'active' => true,
            'name' => self::BASE_PAYEE_NAME,
        ]);

        // Query string is applied
        $response = $this->actingAs($user)->getJson('/api/v1/payees?q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_PAYEE_NAME);

        // Only items of the specific user are returned even if criteria matches items of other user(s)
        $response->assertJsonPath('0.user_id', $user->id);

        // Only active items are returned by default
        // We create a new item for primary user with similar name, but not active, other currency
        AccountEntity::factory()->asPayee($user)->create([
            'active' => false,
            'name' => self::BASE_PAYEE_NAME . ' - inactive',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/payees?q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_PAYEE_NAME);

        // Inactive items can be requested
        $response = $this->actingAs($user)
            ->getJson('/api/v1/payees?withInactive=1&q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);

        // Default limit is applied for number of results
        for ($i = 1; $i <= 20; $i++) {
            AccountEntity::factory()->asPayee($user)->create([
                'active' => true,
                'name' => self::BASE_PAYEE_NAME . ' - clone - ' . $i,
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/payees?q=clone');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(10);
    }

    public function test_transaction_type_must_be_valid_if_provided_for_account_related_call(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // When account_entity_id is provided, controller validates transaction_type
        $response = $this->actingAs($user)->getJson('/api/v1/payees?account_entity_id=1&transaction_type=invalid_type');
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('message', 'The transaction_type parameter is required and must be valid.');

        // A valid transaction type should pass (use 'withdrawal' as in account tests)
        $response = $this->actingAs($user)->getJson('/api/v1/payees?account_entity_id=1&transaction_type=withdrawal');
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_get_payee_default_category_suggestion_returns_dominant_category_for_payee(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $payee = AccountEntity::factory()->asPayee($user, ['category_id' => null])->create([
            'active' => true,
            'name' => 'Dominant Payee',
        ]);

        $account = $this->createAccountEntity($user);

        $dominantCategory = Category::factory()->for($user)->create(['active' => true]);
        $otherCategory = Category::factory()->for($user)->create(['active' => true]);

        foreach (range(1, 6) as $index) {
            $this->createTransactionWithCategory($user, $account->id, $payee->id, $dominantCategory->id, now()->subDays($index));
        }

        foreach (range(1, 2) as $index) {
            $this->createTransactionWithCategory($user, $account->id, $payee->id, $otherCategory->id, now()->subDays(10 + $index));
        }

        $response = $this->actingAs($user)
            ->getJson('/api/v1/payees/category-suggestions/default');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('payee_id', $payee->id);
        $response->assertJsonPath('max_category_id', $dominantCategory->id);
        $response->assertJsonPath('sum', 8);
        $response->assertJsonPath('max', 6);
    }

    public function test_user_can_create_payee_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $attributes = [
            'name' => 'Test Payee',
            'active' => true,
            'config_type' => 'payee',
            'config' => [
                'category_id' => null,
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('api.v1.payees.store'), $attributes);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonPath('name', 'Test Payee');
        $response->assertJsonPath('active', true);

        $this->assertDatabaseHas('account_entities', [
            'name' => 'Test Payee',
            'user_id' => $user->id,
            'config_type' => 'payee',
        ]);
    }

    public function test_user_can_create_payee_with_category_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()->for($user)->create();

        $attributes = [
            'name' => 'Test Payee With Category',
            'active' => true,
            'config_type' => 'payee',
            'config' => [
                'category_id' => $category->id,
            ],
        ];

        $response = $this->actingAs($user)->postJson(route('api.v1.payees.store'), $attributes);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonPath('name', 'Test Payee With Category');
        $response->assertJsonPath('config.category_id', $category->id);
    }

    public function test_user_can_update_payee_with_same_name_and_category_preferences_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $currentDefaultCategory */
        $currentDefaultCategory = Category::factory()->for($user)->create();

        /** @var Category $oldPreferredCategory */
        $oldPreferredCategory = Category::factory()->for($user)->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()
            ->asPayee($user, ['category_id' => $currentDefaultCategory->id])
            ->create([
                'name' => 'Editable Payee',
                'active' => true,
                'alias' => 'initial alias',
            ]);

        $payee->categoryPreference()->sync([
            $oldPreferredCategory->id => ['preferred' => true],
        ]);

        /** @var Category $newDefaultCategory */
        $newDefaultCategory = Category::factory()->for($user)->create();

        /** @var Category $newPreferredCategory */
        $newPreferredCategory = Category::factory()->for($user)->create();

        /** @var Category $newDeferredCategory */
        $newDeferredCategory = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->patchJson(route('api.v1.payees.update', ['accountEntity' => $payee->id]), [
                'name' => 'Editable Payee',
                'active' => false,
                'alias' => 'updated alias',
                'config_type' => 'payee',
                'config' => [
                    'category_id' => $newDefaultCategory->id,
                    'preferred' => [$newPreferredCategory->id],
                    'not_preferred' => [$newDeferredCategory->id],
                ],
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('id', $payee->id);
        $response->assertJsonPath('name', 'Editable Payee');
        $response->assertJsonPath('active', false);
        $response->assertJsonPath('alias', 'updated alias');
        $response->assertJsonPath('config.category_id', $newDefaultCategory->id);

        $this->assertDatabaseHas('account_entities', [
            'id' => $payee->id,
            'name' => 'Editable Payee',
            'active' => false,
            'alias' => 'updated alias',
        ]);

        $this->assertDatabaseHas('payees', [
            'id' => $payee->config_id,
            'category_id' => $newDefaultCategory->id,
        ]);

        $this->assertDatabaseHas('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $newPreferredCategory->id,
            'preferred' => true,
        ]);

        $this->assertDatabaseHas('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $newDeferredCategory->id,
            'preferred' => false,
        ]);

        $this->assertDatabaseMissing('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $oldPreferredCategory->id,
        ]);
    }

    public function test_user_can_update_payee_in_simplified_mode_and_keep_existing_category_preferences_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $currentDefaultCategory */
        $currentDefaultCategory = Category::factory()->for($user)->create();

        /** @var Category $updatedDefaultCategory */
        $updatedDefaultCategory = Category::factory()->for($user)->create();

        /** @var Category $existingPreferredCategory */
        $existingPreferredCategory = Category::factory()->for($user)->create();

        /** @var Category $existingDeferredCategory */
        $existingDeferredCategory = Category::factory()->for($user)->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()
            ->asPayee($user, ['category_id' => $currentDefaultCategory->id])
            ->create([
                'name' => 'Editable Payee',
                'active' => true,
            ]);

        $payee->categoryPreference()->sync([
            $existingPreferredCategory->id => ['preferred' => true],
            $existingDeferredCategory->id => ['preferred' => false],
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.v1.payees.update', ['accountEntity' => $payee->id]), [
                'name' => 'Editable Payee',
                'active' => true,
                'config_type' => 'payee',
                'simplified' => true,
                'config' => [
                    'category_id' => $updatedDefaultCategory->id,
                ],
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('config.category_id', $updatedDefaultCategory->id);

        $this->assertDatabaseHas('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $existingPreferredCategory->id,
            'preferred' => true,
        ]);

        $this->assertDatabaseHas('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $existingDeferredCategory->id,
            'preferred' => false,
        ]);
    }

    public function test_user_can_update_payee_without_preference_keys_and_existing_category_preferences_are_cleared_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $existingPreferredCategory */
        $existingPreferredCategory = Category::factory()->for($user)->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->asPayee($user)->create([
            'name' => 'Editable Payee',
            'active' => true,
        ]);

        $payee->categoryPreference()->sync([
            $existingPreferredCategory->id => ['preferred' => true],
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.v1.payees.update', ['accountEntity' => $payee->id]), [
                'name' => 'Editable Payee',
                'active' => true,
                'config_type' => 'payee',
                'config' => [
                    'category_id' => null,
                ],
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $existingPreferredCategory->id,
        ]);
    }

    public function test_user_can_update_payee_active_flag_via_account_entity_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->asPayee($user)->create();

        $response = $this->actingAs($user)->patchJson(
            route('api.v1.account-entities.patch-active', [
                'accountEntity' => $payee->id,
            ]),
            [
                'active' => false,
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('id', $payee->id);
        $response->assertJsonPath('active', false);

        $this->assertDatabaseHas('account_entities', [
            'id' => $payee->id,
            'active' => false,
        ]);
    }

    public function test_user_can_accept_payee_category_suggestion_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()->for($user)->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->asPayee($user)->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.payees.category-suggestions.accept', [
            'accountEntity' => $payee->id,
            'category' => $category->id,
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $payee->refresh();
        $this->assertEquals($category->id, $payee->config->category_id);
    }

    public function test_user_cannot_update_other_users_payee(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();

        /** @var User $user2 */
        $user2 = User::factory()->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->asPayee($user1)->create();

        $response = $this->actingAs($user2)->patchJson(
            route('api.v1.payees.update', [
                'accountEntity' => $payee->id,
            ]),
            [
                'name' => $payee->name,
                'config_type' => 'payee',
                'active' => false,
            ]
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
}
