<?php

namespace App\Console\Commands;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only pre-migration audit for the budget/schedule redesign
 * (see .ai/docs/specifications/budget-schedule-redesign/specification.md, Section 7.1).
 *
 * Reports budget-only transactions (schedule=false, budget=true) that the eventual
 * transforming migration (Section 7.2) cannot safely convert into a `Budget` row
 * without either losing or misattributing data. This command makes no changes.
 */
class CheckBudgetMigration extends Command
{
    use CurrencyTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check:budget-migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read-only audit for the upcoming budget/schedule redesign: reports budget-only transactions that would be lost or misattributed by the transforming migration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $zeroItemTransactions = $this->findZeroItemTransactions();
        $payeeAttributedTransactions = $this->findPayeeAttributedTransactions();
        $strayNonStandardTransactions = $this->findStrayNonStandardTransactions();
        $currencyMismatchTransactions = $this->findCurrencyMismatchTransactions();

        $this->info('Budget/schedule redesign pre-migration check');

        $this->reportCase(
            'Budget-only transactions with zero transaction items',
            $zeroItemTransactions
        );
        $this->reportCase(
            'Budget-only transactions attributed to a payee instead of a real account',
            $payeeAttributedTransactions
        );
        $this->reportCase(
            'Transfer/investment transactions incorrectly flagged as budget',
            $strayNonStandardTransactions
        );
        $this->reportCase(
            "Budget-only transactions whose currency doesn't match their linked account (or base currency)",
            $currencyMismatchTransactions
        );

        $totalIssues = $zeroItemTransactions->count()
            + $payeeAttributedTransactions->count()
            + $strayNonStandardTransactions->count()
            + $currencyMismatchTransactions->count();

        $this->newLine();

        if ($totalIssues > 0) {
            $this->error("{$totalIssues} issue(s) found across the cases above. Resolve them before the budget/schedule migration (see specification.md, Section 7) is allowed to run.");

            return self::FAILURE;
        }

        $this->info('No migration-blocking issues found.');

        return self::SUCCESS;
    }

    /**
     * Base query for budget-only transactions: not a schedule, but flagged as a budget.
     * These are the only rows Section 7.2's transforming migration ever converts.
     *
     * @return Builder<Transaction>
     */
    private function budgetOnlyQuery(): Builder
    {
        return Transaction::query()
            ->where('schedule', false)
            ->where('budget', true);
    }

    /**
     * Budget-only transactions restricted to standard withdrawal/deposit rows —
     * the only shape Section 7.2's per-category item transform actually processes.
     * Transfers (no items) and investments are handled by findStrayNonStandardTransactions() instead.
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
     * Case 1: a budget-only transaction with no transaction items.
     * The naive transform produces zero `Budget` rows for these, silently
     * discarding the amount/schedule entirely.
     *
     * @return Collection<int, Transaction>
     */
    private function findZeroItemTransactions(): Collection
    {
        return $this->standardBudgetOnlyQuery()
            ->whereDoesntHave('transactionItems')
            ->get(['id']);
    }

    /**
     * Case 2: a budget-only transaction whose "account" side (account_from_id for a
     * withdrawal, account_to_id for a deposit — the other side is the payee, which is
     * expected to be a payee and is not itself a problem) actually resolves to a payee,
     * not a real account. Carrying a payee into `Budget.account_id` (which must only
     * ever reference a real account) would be wrong.
     *
     * @return Collection<int, Transaction>
     */
    private function findPayeeAttributedTransactions(): Collection
    {
        return $this->standardBudgetOnlyQuery()
            ->with(['config.accountFrom', 'config.accountTo'])
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->accountSideEntity($transaction)?->isPayee() ?? false)
            ->values();
    }

    /**
     * Resolves the AccountEntity on the side of account_from_id/account_to_id that is
     * expected to hold the real account (not the payee) for a standard withdrawal/deposit —
     * the side that Section 7.2 carries over into `Budget.account_id` if it is a real account.
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
     * Case 3: a transfer or investment transaction with budget=true.
     * Business rules say this should never happen (the frontend disables the
     * budget checkbox for transfers and never shows it for investments), but it
     * was never enforced at the backend. A transfer has no items to transform,
     * and an investment transaction is outside what Section 7.2 processes at all.
     *
     * @return Collection<int, Transaction>
     */
    private function findStrayNonStandardTransactions(): Collection
    {
        return $this->budgetOnlyQuery()
            ->where(function (Builder $query): void {
                $query->where('config_type', 'investment')
                    ->orWhere(function (Builder $query): void {
                        $query->where('config_type', 'standard')
                            ->where('transaction_type', TransactionTypeEnum::TRANSFER);
                    });
            })
            ->get(['id', 'config_type', 'transaction_type']);
    }

    /**
     * Case 4: a budget-only transaction whose *effective* currency doesn't match the
     * currency it would resolve to once converted (its linked account's current
     * currency, or the user's base currency if account-agnostic). Since `Budget`
     * computes its currency rather than storing one (FR-4), a pre-existing mismatch
     * would be silently discarded by adopting the account's currency going forward.
     *
     * A null `currency_id` is not itself a problem — it's the existing, documented way
     * a transaction says "use the base currency" (see `Transaction::getTransactionCurrencyAttribute()`),
     * and is exactly what an account-agnostic budget-only transaction is expected to have.
     * So the comparison uses `transaction_currency` (the already-resolved effective
     * currency), not the raw `currency_id` column.
     *
     * @return Collection<int, Transaction>
     */
    private function findCurrencyMismatchTransactions(): Collection
    {
        return $this->standardBudgetOnlyQuery()
            ->with(['config.accountFrom.config', 'config.accountTo.config'])
            ->get()
            ->filter(function (Transaction $transaction): bool {
                $accountSideEntity = $this->accountSideEntity($transaction);
                $linkedAccountConfig = $accountSideEntity?->isAccount() === true ? $accountSideEntity->config : null;
                $linkedAccount = $linkedAccountConfig instanceof Account ? $linkedAccountConfig : null;

                $expectedCurrencyId = $linkedAccount
                    ? $linkedAccount->currency_id
                    : $this->getBaseCurrency($transaction->user_id)?->id;

                $actualCurrencyId = $transaction->transaction_currency?->id;

                return $expectedCurrencyId !== null && $actualCurrencyId !== $expectedCurrencyId;
            })
            ->values();
    }

    /**
     * Print a single risk case's finding count and, if any, the affected transaction IDs.
     *
     * @param Collection<int, Transaction> $transactions
     */
    private function reportCase(string $label, Collection $transactions): void
    {
        $this->newLine();
        $this->line("<comment>{$label}:</comment> {$transactions->count()} found");

        if ($transactions->isNotEmpty()) {
            $this->line('  Transaction IDs: ' . $transactions->pluck('id')->implode(', '));
        }
    }
}
