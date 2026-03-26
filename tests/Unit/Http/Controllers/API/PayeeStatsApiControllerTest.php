<?php

namespace Tests\Unit\Http\Controllers\API;

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

    public function test_category_stats_returns_aggregated_categories_for_last_six_months(): void
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

        $payee = AccountEntity::factory()
            ->for($otherUser)
            ->for(Payee::factory()->withUser($otherUser), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
            ]);

        $response = $this->actingAs($user)
            ->getJson(
                route('api.v1.payees.category-stats', ['accountEntity' => $payee->id])
            );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private function createTransactionWithCategory(
        User $user,
        int $accountId,
        int $payeeId,
        int $categoryId,
        \Carbon\Carbon $date
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
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
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
