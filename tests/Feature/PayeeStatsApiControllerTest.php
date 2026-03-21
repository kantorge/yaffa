<?php

namespace Tests\Feature;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class PayeeStatsApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_stats_returns_category_full_name_and_deferred_ids(): void
    {
        $user = User::factory()->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Coffee Shop',
            ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);

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

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
            ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);

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

    private function createTransactionWithCategory(
        User $user,
        int $accountId,
        int $payeeId,
        int $categoryId,
        \Carbon\Carbon $date,
        TransactionTypeEnum $transactionType = TransactionTypeEnum::WITHDRAWAL,
    ): void {
        $detail = TransactionDetailStandard::query()->create([
            'account_from_id' => $accountId,
            'account_to_id' => $payeeId,
            'amount_from' => 10,
            'amount_to' => 10,
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'transaction_type' => $transactionType->value,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'comment' => null,
            'config_type' => 'standard',
            'config_id' => $detail->id,
        ]);

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryId,
            'amount' => 10,
            'comment' => 'Test item',
        ]);
    }
}
