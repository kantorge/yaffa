<?php

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\AccountEntity;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;

/**
 * Converts every remaining schedule=false, budget=true "fake transaction" (the legacy way of
 * storing a standalone budget) into one standalone `Budget` row per distinct category among its
 * transaction items, then hard-deletes the source transaction. See specification.md Section 7.2.
 *
 * Also converts the narrow set of legacy schedule=true, budget=true rows with no real account on
 * either side (account_from_id and account_to_id both null) — these predate this redesign's
 * requirement that a schedule transaction always reference real accounts, could never have fired as
 * a real recorded transaction (no account to record against), and are functionally indistinguishable
 * from a schedule=false budget-only row. The spec's Section 7.2 text ("schedule=true transactions:
 * no data movement needed") assumes a schedule transaction always has real accounts, which this
 * narrow, additional case violates.
 *
 * Refuses to run if any of the 4 risk cases from Section 7.1 are present (mirrored here from the
 * `app:check:budget-migration` command, which is removed in this same release once this migration
 * makes it dead code — see Section 7.1's rollout note), since none of them can be converted without
 * losing or misattributing data.
 */
return new class () extends Migration {
    public function up(): void
    {
        $this->guardAgainstUnsafeData();

        $this->budgetOnlyQuery()
            ->with([
                'config.accountFrom',
                'config.accountTo',
                'transactionItems',
                'transactionSchedule',
            ])
            ->get()
            ->each(function (Transaction $transaction): void {
                $this->convertTransaction($transaction);
            });
    }

    public function down(): void
    {
        // Reverting the data transformation is not supported — see specification.md Section 7.3.
        // Reconstructing the original budget-only transactions from `Budget` rows would require
        // lineage back to a source transaction/category that this migration deliberately discards
        // by hard-deleting the source rows, and that stops being meaningful the moment a user edits
        // a converted `Budget` row through the new UI.
    }

    /**
     * Convert a single budget-only transaction into one `Budget` row per distinct category among
     * its items (summing amounts within the same category), then hard-delete the transaction.
     */
    private function convertTransaction(Transaction $transaction): void
    {
        $config = $transaction->config;

        if (! $config instanceof TransactionDetailStandard) {
            throw new RuntimeException(
                "Budget-only transaction {$transaction->id} does not have a standard config; "
                . 'the pre-migration guard should have caught this.'
            );
        }

        $schedule = $transaction->transactionSchedule;

        if ($schedule === null) {
            throw new RuntimeException(
                "Budget-only transaction {$transaction->id} has no linked schedule; "
                . 'cannot determine recurrence for the converted Budget row(s).'
            );
        }

        $accountSideEntity = $this->accountSideEntity($transaction);
        $accountId = $accountSideEntity?->isAccount() === true ? $accountSideEntity->id : null;

        $transaction->transactionItems
            ->groupBy('category_id')
            ->each(function (Collection $items, int $categoryId) use ($transaction, $accountId, $schedule): void {
                $budget = new Budget([
                    'category_id' => $categoryId,
                    'account_id' => $accountId,
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => $items->sum('amount'),
                    'frequency' => $schedule->frequency,
                    'interval' => $schedule->interval,
                    'start_date' => $schedule->start_date,
                    'end_date' => $schedule->end_date,
                    'count' => $schedule->count,
                    'inflation' => $schedule->inflation,
                ]);
                // Not mass-assignable (ModelOwnedByUserTrait only auto-fills it from an
                // authenticated user, which does not exist in a console/migration context).
                $budget->user_id = $transaction->user_id;
                $budget->save();
            });

        // Hard delete, per Section 7.2: these rows represent nothing once `budget` is removed as a
        // concept. transaction_items and transaction_schedules cascade via FK; config does not
        // (polymorphic relation, no FK), so it must be deleted explicitly.
        $config->delete();
        $transaction->delete();
    }

    /**
     * Resolves the AccountEntity on the side of account_from_id/account_to_id that is expected to
     * hold the real account (not the payee) for a standard withdrawal/deposit — the side that gets
     * carried into `Budget.account_id` if it is a real account.
     */
    private function accountSideEntity(Transaction $transaction): ?AccountEntity
    {
        $config = $transaction->config;

        if (! $config instanceof TransactionDetailStandard) {
            return null;
        }

        return $transaction->transaction_type === TransactionTypeEnum::WITHDRAWAL
            ? $config->accountFrom
            : $config->accountTo;
    }

    /**
     * Base query for budget-only transactions: either not a schedule at all, or a legacy
     * schedule+budget hybrid with no real account on either side (see class docblock). Both are
     * budget=true rows with no way to ever become a real recorded transaction, and are the only
     * rows this migration ever converts.
     *
     * @return Builder<Transaction>
     */
    private function budgetOnlyQuery(): Builder
    {
        return Transaction::query()
            ->where('budget', true)
            ->where(function (Builder $query): void {
                $query->where('schedule', false)
                    ->orWhere(function (Builder $query): void {
                        $query->where('schedule', true)
                            ->whereHasMorph('config', [TransactionDetailStandard::class], function (Builder $query): void {
                                $query->whereNull('account_from_id')
                                    ->whereNull('account_to_id');
                            });
                    });
            });
    }

    /**
     * Budget-only transactions restricted to standard withdrawal/deposit rows — the only shape this
     * migration's per-category item transform actually processes. Transfers (no items) and
     * investments are handled by findStrayNonStandardTransactions() instead.
     *
     * @return Builder<Transaction>
     */
    private function standardBudgetOnlyQuery(): Builder
    {
        return $this->budgetOnlyQuery()
            ->where('config_type', 'standard')
            ->whereIn('transaction_type', [
                TransactionTypeEnum::WITHDRAWAL->value,
                TransactionTypeEnum::DEPOSIT->value,
            ]);
    }

    /**
     * Refuses to proceed (throws) if any of the 4 risk cases from Section 7.1 are found. No user
     * data is silently dropped or misattributed by this migration.
     */
    private function guardAgainstUnsafeData(): void
    {
        $zeroItemCount = $this->standardBudgetOnlyQuery()
            ->whereDoesntHave('transactionItems')
            ->count();

        $payeeAttributedCount = $this->standardBudgetOnlyQuery()
            ->with(['config.accountFrom', 'config.accountTo'])
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->accountSideEntity($transaction)?->isPayee() ?? false)
            ->count();

        $strayNonStandardCount = $this->budgetOnlyQuery()
            ->where(function (Builder $query): void {
                $query->where('config_type', 'investment')
                    ->orWhere(function (Builder $query): void {
                        $query->where('config_type', 'standard')
                            ->where('transaction_type', TransactionTypeEnum::TRANSFER);
                    });
            })
            ->count();

        $currencyMismatchCount = $this->standardBudgetOnlyQuery()
            ->with(['config.accountFrom.config', 'config.accountTo.config'])
            ->get()
            ->filter(function (Transaction $transaction): bool {
                $accountSideEntity = $this->accountSideEntity($transaction);
                $linkedAccountConfig = $accountSideEntity?->isAccount() === true ? $accountSideEntity->config : null;
                $linkedAccount = $linkedAccountConfig instanceof App\Models\Account ? $linkedAccountConfig : null;

                $expectedCurrencyId = $linkedAccount
                    ? $linkedAccount->currency_id
                    : $transaction->getBaseCurrency($transaction->user_id)?->id;

                $actualCurrencyId = $transaction->transaction_currency?->id;

                return $expectedCurrencyId !== null && $actualCurrencyId !== $expectedCurrencyId;
            })
            ->count();

        $totalIssues = $zeroItemCount + $payeeAttributedCount + $strayNonStandardCount + $currencyMismatchCount;

        if ($totalIssues > 0) {
            throw new RuntimeException(
                "Budget/schedule redesign migration blocked: {$totalIssues} issue(s) found in "
                . 'budget-only transactions (zero-item, payee-attributed, stray transfer/investment, '
                . 'or currency-mismatch cases). Run `php artisan app:check:budget-migration` on the '
                . 'pre-upgrade release to identify and resolve them before upgrading.'
            );
        }
    }
};
