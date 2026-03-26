<?php

namespace Tests\Unit\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_duplicates_uses_user_date_window(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'duplicate_date_window_days' => 1,
            'duplicate_amount_tolerance_percent' => 10,
            'duplicate_similarity_threshold' => 0.0,
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
            ]);

        $this->createStandardTransaction(
            user: $user,
            accountFromId: $account->id,
            accountToId: $payee->id,
            amount: 50,
            date: now()->subDays(3),
        );

        $service = new DuplicateDetectionService();
        $matches = $service->findDuplicates($user, [
            'date' => now()->toDateString(),
            'amount' => 50,
            'config_type' => 'standard',
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->assertSame([], $matches);
    }

    public function test_find_duplicates_uses_user_similarity_threshold(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'duplicate_date_window_days' => 7,
            'duplicate_amount_tolerance_percent' => 10,
            'duplicate_similarity_threshold' => 1.0,
        ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
            ]);

        $transaction = $this->createStandardTransaction(
            user: $user,
            accountFromId: $account->id,
            accountToId: $payee->id,
            amount: 99,
            date: now(),
        );

        $service = new DuplicateDetectionService();
        $matches = $service->findDuplicates($user, [
            'date' => now()->toDateString(),
            'amount' => 99,
            'config_type' => 'standard',
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->assertSame([], $matches);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
        ]);
    }

    private function createStandardTransaction(
        User $user,
        int $accountFromId,
        int $accountToId,
        float $amount,
        Carbon $date,
    ): Transaction {
        $detail = TransactionDetailStandard::query()->create([
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'amount_from' => $amount,
            'amount_to' => $amount,
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

        $category = Category::factory()->for($user)->create([
            'active' => true,
        ]);

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'comment' => 'Duplicate detection test item',
        ]);

        return $transaction;
    }
}
