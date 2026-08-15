<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnedAccount(User $user): AccountEntity
    {
        AccountGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $account = Account::factory()->withUser($user)->create();

        return AccountEntity::factory()->for($user)->for($account, 'config')->create();
    }

    public function test_guest_cannot_access_budgets(): void
    {
        $this->getJson(route('api.v1.budgets.index'))->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->postJson(route('api.v1.budgets.store'), [])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_user_can_create_an_account_agnostic_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 250.50,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('category_id', $category->id)
            ->assertJsonPath('account_id', null)
            ->assertJsonPath('active', true);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
        ]);
    }

    public function test_user_can_create_an_account_scoped_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $accountEntity = $this->createOwnedAccount($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'account_id' => $accountEntity->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('account_id', $accountEntity->id);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'account_id' => $accountEntity->id,
        ]);
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 0,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_user_cannot_use_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $otherCategory->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_user_cannot_use_a_payee_as_the_account(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'account_id' => $payee->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_end_date_and_count_are_mutually_exclusive(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addYear()->toDateString(),
            'count' => 5,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_transaction_type_must_be_withdrawal_or_deposit(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'transfer',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['transaction_type']);
    }

    public function test_user_can_create_a_deposit_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'deposit',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('transaction_type', 'deposit');
    }

    public function test_active_flag_cannot_be_set_by_the_client(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        // A rule that has already run out of occurrences: the client-sent `active: true` must be ignored.
        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'DAILY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->subDay()->toDateString(),
            'active' => true,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('active', false);
    }

    public function test_user_can_view_a_single_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson(route('api.v1.budgets.show', ['budget' => $budget->id]))
            ->assertOk()
            ->assertJsonPath('id', $budget->id);
    }

    public function test_user_can_update_a_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson(route('api.v1.budgets.update', ['budget' => $budget->id]), [
            'category_id' => $category->id,
            'transaction_type' => $budget->transaction_type->value,
            'amount' => 200,
            'frequency' => $budget->frequency,
            'interval' => $budget->interval,
            'start_date' => $budget->start_date->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('amount', 200);
        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'amount' => 200]);
    }

    public function test_user_can_delete_a_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->deleteJson(route('api.v1.budgets.destroy', ['budget' => $budget->id]))->assertOk();

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_user_cannot_manage_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $budget = Budget::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $ownerCategory->id,
        ]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Sanctum::actingAs($otherUser, ['*']);

        $this->getJson(route('api.v1.budgets.show', ['budget' => $budget->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->patchJson(route('api.v1.budgets.update', ['budget' => $budget->id]), [
            'category_id' => $otherCategory->id,
            'transaction_type' => 'withdrawal',
            'amount' => 50,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->toDateString(),
        ])->assertStatus(Response::HTTP_FORBIDDEN);

        $this->deleteJson(route('api.v1.budgets.destroy', ['budget' => $budget->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_index_returns_only_the_authenticated_users_budgets(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Budget::factory()->create(['user_id' => $otherUser->id, 'category_id' => $otherCategory->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.budgets.index'));

        $response->assertOk()->assertJsonCount(1);
    }
}
