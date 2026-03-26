<?php

namespace Tests\Unit;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\PayeeCategoryStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayeeCategoryStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_category_stats_for_payee_returns_full_name_field(): void
    {
        $service = new PayeeCategoryStatsService();
        $user = User::factory()->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create(['config_type' => 'payee']);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create(['config_type' => 'account']);

        $category = Category::factory()->for($user)->create([
            'active' => 1,
            'name' => 'Food',
        ]);

        $this->createTransactionWithCategory($user, $account->id, $payee->id, $category->id);

        $stats = $service->getCategoryStatsForPayee($user, $payee, 6);

        $this->assertCount(1, $stats);
        $this->assertSame($category->id, $stats[0]['category_id']);
        $this->assertSame(1, $stats[0]['usage_count']);
        $this->assertSame($category->full_name, $stats[0]['category_full_name']);
    }

    public function test_get_category_stats_for_payee_can_filter_by_transaction_type(): void
    {
        $service = new PayeeCategoryStatsService();
        $user = User::factory()->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create(['config_type' => 'payee']);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create(['config_type' => 'account']);

        $expenseCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Expense']);
        $incomeCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Income']);

        $this->createTransactionWithCategory(
            $user,
            $account->id,
            $payee->id,
            $expenseCategory->id,
            TransactionTypeEnum::WITHDRAWAL,
        );

        $this->createTransactionWithCategory(
            $user,
            $account->id,
            $payee->id,
            $incomeCategory->id,
            TransactionTypeEnum::DEPOSIT,
        );

        $withdrawalStats = $service->getCategoryStatsForPayee(
            $user,
            $payee,
            6,
            TransactionTypeEnum::WITHDRAWAL,
        );

        $this->assertCount(1, $withdrawalStats);
        $this->assertSame($expenseCategory->id, $withdrawalStats[0]['category_id']);

        $depositStats = $service->getCategoryStatsForPayee(
            $user,
            $payee,
            6,
            TransactionTypeEnum::DEPOSIT,
        );

        $this->assertCount(1, $depositStats);
        $this->assertSame($incomeCategory->id, $depositStats[0]['category_id']);
    }

    private function createTransactionWithCategory(
        User $user,
        int $accountId,
        int $payeeId,
        int $categoryId,
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
            'date' => now(),
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
