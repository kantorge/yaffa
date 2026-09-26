<?php

namespace Tests\Unit;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Category;
use App\Models\User;
use App\Services\PayeeCategoryStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTransactions;
use Tests\TestCase;

class PayeeCategoryStatsServiceTest extends TestCase
{
    use CreatesTestTransactions;
    use RefreshDatabase;

    public function test_get_category_stats_for_payee_returns_full_name_field(): void
    {
        $service = new PayeeCategoryStatsService();
        $user = User::factory()->create();

        $payee = $this->createPayeeEntity($user);
        $account = $this->createAccountEntity($user);

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

        $payee = $this->createPayeeEntity($user);
        $account = $this->createAccountEntity($user);

        $expenseCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Expense']);
        $incomeCategory = Category::factory()->for($user)->create(['active' => 1, 'name' => 'Income']);

        $this->createTransactionWithCategory(
            $user,
            $account->id,
            $payee->id,
            $expenseCategory->id,
            transactionType: TransactionTypeEnum::WITHDRAWAL,
        );

        $this->createTransactionWithCategory(
            $user,
            $account->id,
            $payee->id,
            $incomeCategory->id,
            transactionType: TransactionTypeEnum::DEPOSIT,
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
}
