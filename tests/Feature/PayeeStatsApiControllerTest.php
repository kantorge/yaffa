<?php

namespace Tests\Feature;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Concerns\CreatesTestTransactions;
use Tests\TestCase;

class PayeeStatsApiControllerTest extends TestCase
{
    use CreatesTestTransactions;
    use RefreshDatabase;

    public function test_category_stats_returns_category_full_name_and_deferred_ids(): void
    {
        $user = User::factory()->create();

        $payee = $this->createPayeeEntity($user, ['active' => true, 'name' => 'Coffee Shop']);

        $account = $this->createAccountEntity($user);

        $primaryCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Food']);
        $secondaryCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Drinks']);

        $payee->deferredCategories()->attach($secondaryCategory->id, ['preferred' => false]);

        $this->createTransactionWithCategory($user, $account->id, $payee->id, $primaryCategory->id, now()->subMonth());

        $response = $this->actingAs($user)
            ->getJson(route('api.v1.payees.category-stats', ['accountEntity' => $payee->id]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('categories.0.category_id', $primaryCategory->id);
        $response->assertJsonPath('categories.0.category_full_name', $primaryCategory->full_name);
        $response->assertJsonPath('deferred_category_ids.0', $secondaryCategory->id);
    }

    public function test_category_stats_filters_by_transaction_type(): void
    {
        $user = User::factory()->create();

        $payee = $this->createPayeeEntity($user, ['active' => true]);

        $account = $this->createAccountEntity($user);

        $expenseCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Expense']);
        $incomeCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Income']);

        $this->createTransactionWithCategory(
            $user,
            $account->id,
            $payee->id,
            $expenseCategory->id,
            now()->subMonth(),
            TransactionTypeEnum::WITHDRAWAL,
        );

        $this->createTransactionWithCategory(
            $user,
            $account->id,
            $payee->id,
            $incomeCategory->id,
            now()->subWeeks(2),
            TransactionTypeEnum::DEPOSIT,
        );

        $withdrawalResponse = $this->actingAs($user)
            ->getJson(route('api.v1.payees.category-stats', [
                'accountEntity' => $payee->id,
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            ]));

        $withdrawalResponse->assertStatus(Response::HTTP_OK);
        $withdrawalResponse->assertJsonCount(1, 'categories');
        $withdrawalResponse->assertJsonPath('categories.0.category_id', $expenseCategory->id);

        $depositResponse = $this->actingAs($user)
            ->getJson(route('api.v1.payees.category-stats', [
                'accountEntity' => $payee->id,
                'transaction_type' => TransactionTypeEnum::DEPOSIT->value,
            ]));

        $depositResponse->assertStatus(Response::HTTP_OK);
        $depositResponse->assertJsonCount(1, 'categories');
        $depositResponse->assertJsonPath('categories.0.category_id', $incomeCategory->id);
    }

    public function test_category_stats_returns_aggregated_categories_for_last_six_months(): void
    {
        $user = User::factory()->create();

        $payee = $this->createPayeeEntity($user, ['active' => true, 'name' => 'Coffee Shop']);

        $account = $this->createAccountEntity($user);

        $primaryCategory = Category::factory()->for($user)->create(['active' => 1]);
        $secondaryCategory = Category::factory()->for($user)->create(['active' => 1]);
        $oldCategory = Category::factory()->for($user)->create(['active' => 1]);

        $this->createTransactionWithCategory($user, $account->id, $payee->id, $primaryCategory->id, now()->subMonths(1));
        $this->createTransactionWithCategory($user, $account->id, $payee->id, $primaryCategory->id, now()->subMonths(2));
        $this->createTransactionWithCategory($user, $account->id, $payee->id, $secondaryCategory->id, now()->subMonths(3));
        $this->createTransactionWithCategory($user, $account->id, $payee->id, $oldCategory->id, now()->subMonths(8));

        $response = $this->actingAs($user)
            ->getJson(
                route('api.v1.payees.category-stats', ['accountEntity' => $payee->id])
            );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('payee_id', $payee->id);
        $response->assertJsonPath('payee_name', 'Coffee Shop');
        $response->assertJsonPath('period_months', 6);
        $response->assertJsonCount(2, 'categories');
        $response->assertJsonPath('categories.0.category_id', $primaryCategory->id);
        $response->assertJsonPath('categories.0.usage_count', 2);
        $response->assertJsonPath('categories.1.category_id', $secondaryCategory->id);
        $response->assertJsonPath('categories.1.usage_count', 1);
    }

    public function test_category_stats_returns_not_found_for_non_owned_payee(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $payee = $this->createPayeeEntity($otherUser, ['active' => true]);

        $response = $this->actingAs($user)
            ->getJson(
                route('api.v1.payees.category-stats', ['accountEntity' => $payee->id])
            );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
