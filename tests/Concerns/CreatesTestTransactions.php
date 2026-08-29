<?php

namespace Tests\Concerns;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;

/**
 * Deterministic standard-transaction creation for tests that assert on specific
 * categories/dates/amounts, which TransactionFactory can't provide since its
 * afterCreating() hook always attaches randomly-categorized transaction items.
 */
trait CreatesTestTransactions
{
    protected function createAccountEntity(User $user, array $attributes = []): AccountEntity
    {
        return AccountEntity::factory()
            ->asAccount($user)
            ->create(array_merge(['active' => true], $attributes));
    }

    protected function createPayeeEntity(User $user, array $attributes = []): AccountEntity
    {
        return AccountEntity::factory()
            ->asPayee($user)
            ->create($attributes);
    }

    protected function createTransactionWithCategory(
        User $user,
        int $accountId,
        int $payeeId,
        int $categoryId,
        Carbon|string|null $date = null,
        TransactionTypeEnum $transactionType = TransactionTypeEnum::WITHDRAWAL,
    ): Transaction {
        return $this->createStandardTransaction(
            user: $user,
            accountFromId: $accountId,
            accountToId: $payeeId,
            amount: 10,
            date: $date ?? now(),
            transactionType: $transactionType,
            categoryId: $categoryId,
        );
    }

    protected function createStandardTransaction(
        User $user,
        int $accountFromId,
        ?int $accountToId,
        float $amount,
        Carbon|string $date,
        TransactionTypeEnum $transactionType = TransactionTypeEnum::WITHDRAWAL,
        string $comment = 'Test item',
        ?int $categoryId = null,
    ): Transaction {
        $accountToId ??= $this->createPayeeEntity($user)->id;

        $detail = TransactionDetailStandard::factory()->create([
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ]);

        $transaction = new Transaction([
            'date' => $date,
            'transaction_type' => $transactionType->value,
            'reconciled' => false,
            'schedule' => false,
            'comment' => null,
            'config_type' => 'standard',
            'config_id' => $detail->id,
        ]);
        $transaction->user_id = $user->id;
        $transaction->save();

        $categoryId ??= Category::factory()->for($user)->create(['active' => true])->id;

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryId,
            'amount' => $amount,
            'comment' => $comment,
        ]);

        return $transaction;
    }
}
