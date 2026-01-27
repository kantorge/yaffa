<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\AccountBalanceCheckpoint;
use Illuminate\Database\Eloquent\Builder;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class MainController extends Controller implements HasMiddleware
{
    use CurrencyTrait;
    use ScheduleTrait;
    use AuthorizesRequests;

    private $allAccounts;

    private $currentAccount;

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    public function account_details(Request $request, AccountEntity $account, $withForecast = null)
    {
        /**
         * @get('/account/history/{account}/{withForecast?}')
         * @name('account.history')
         * @middlewares('web', 'auth', 'verified')
         */
        $user = $request->user();

        // Get account details and load to class variable
        $this->currentAccount = $account->load([
            'config',
            'config.currency',
        ]);

        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', $user->id)
            ->pluck('name', 'id')
            ->all();

        // Get standard transactions related to selected account (one-time AND scheduled)
        $standardTransactions = Transaction::where(function ($query) {
            $query->where('schedule', 1)
                ->orWhere(function ($query) {
                    $query->byScheduleType('none');
                });
        })
            ->where('user_id', $user->id)
            ->whereHasMorph(
                'config',
                [TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->with([
                'config',
                'transactionType',
                'transactionItems',
                'transactionItems.category',
                'transactionItems.tags',
            ])
            ->get();

        // Get all investment transactions related to selected account (one-time AND scheduled)
        $investmentTransactions = Transaction::where(function ($query) {
            $query->where('schedule', 1)
                ->orWhere(function ($query) {
                    $query->byScheduleType('none');
                });
        })
            ->where('user_id', $user->id)
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($account) {
                    $query->where('account_id', $account->id);
                }
            )
            ->with([
                'config',
                'config.investment',
                'transactionType',
            ])
            ->get();

        // Unify and merge two transaction types
        $transactions = $standardTransactions
            ->concat($investmentTransactions)
            // Add custom and pre-calculated attributes
            ->map(function ($transaction) use ($account) {
                if ($transaction->schedule) {
                    $transaction->load(['transactionSchedule']);

                    $transaction->transactionGroup = 'schedule';
                } else {
                    $transaction->transactionGroup = 'history';
                }

                if ($transaction->isStandard()) {
                    $transaction->transactionOperator = $transaction->transactionType->amount_multiplier
                        ?? ($transaction->config->account_from_id === $this->currentAccount->id ? -1 : 1);
                    $transaction->account_from_name = $this->allAccounts[$transaction->config->account_from_id];
                    $transaction->account_to_name = $this->allAccounts[$transaction->config->account_to_id];
                    $transaction->amount_from = $transaction->config->amount_from;
                    $transaction->amount_to = $transaction->config->amount_to;
                    $transaction->tags = $transaction->tags()->values();
                    $transaction->categories = $transaction->categories()->values();
                } elseif ($transaction->isInvestment()) {
                    $amount = $transaction->cashflow_value ?? 0;

                    $transaction->transactionOperator = $transaction->transactionType->amount_multiplier;
                    $transaction->account_from_name = $this->allAccounts[$transaction->config->account_id];
                    $transaction->account_to_name = $transaction->config->investment->name;
                    $transaction->amount_from = ($amount < 0 ? -$amount : null);
                    $transaction->amount_to = ($amount > 0 ? $amount : null);
                    $transaction->amount = ($amount !== 0 ? $amount : null);
                    $transaction->tags = [];
                    $transaction->categories = [];
                    $transaction->quantity = $transaction->config->quantity;
                    $transaction->price = $transaction->config->price;
                    $transaction->currency = $account->config->currency;
                }

                return $transaction;
            })
            // Drop scheduled transactions, which are not active (next date is empty)
            ->filter(fn ($transaction) => !$transaction->schedule || $transaction->transactionSchedule->next_date !== null);

        // Add schedule to history items, if needeed
        if ($withForecast) {
            $transactions = $transactions->concat(
                $this->getScheduleInstances(
                    $transactions
                        ->filter(fn ($transaction) => $transaction->schedule),
                    'next',
                )
            );
        }

        // Final ordering and running total calculation
        $subTotal = 0;

        $data = $transactions
            ->filter(
                fn ($transaction) =>
                $transaction->transactionGroup === 'history'
                || $transaction->transactionGroup === 'forecast'
            )
            ->sortByDesc('transactionType')
            ->sortBy(['date', 'transactionType.amount_multiplier'])
            // Add the opening balance dummy item to the beginning of transaction list
            ->prepend($account->config->openingBalance())
            ->map(function ($transaction) use (&$subTotal) {
                $subTotal += ($transaction->transactionOperator === 1
                    ? $transaction->amount_to
                    : -1 * $transaction->amount_from);
                $transaction->running_total = $subTotal;

                return $transaction;
            })
            ->values();

        JavaScriptFacade::put([
            'currency' => $account->config->currency,
            'transactionData' => $data,
            'scheduleData' => $transactions
                ->filter(fn ($transaction) => $transaction->transactionGroup === 'schedule')
                ->values(),
        ]);

        return view(
            'account.history',
            [
                'account' => $account,
                'withForecast' => $withForecast,
            ]
        );
    }

    public function account_reconcile(Request $request, AccountEntity $account)
    {
        /**
         * @get('/account/reconcile/{account}')
         * @name('account.reconcile')
         * @middlewares('web', 'auth', 'verified')
         */
        
        // Authorize access
        $this->authorize('view', $account);
        
        $user = $request->user();

        // Get account details
        $this->currentAccount = $account->load([
            'config',
            'config.currency',
        ]);

        // Get all accounts and payees for display names
        $this->allAccounts = AccountEntity::where('user_id', $user->id)
            ->pluck('name', 'id')
            ->all();

        // Get date range from request (default to current month)
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        \Log::info('Reconcile page loaded', [
            'account_id' => $account->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_id' => $user->id,
        ]);

        // Calculate opening balance (balance at start date, excluding transactions on start date)
        $openingBalance = $this->calculateBalanceBeforeDate($account, $startDate);

        // Get standard transactions within date range
        $standardTransactions = Transaction::where('schedule', false)
            ->where('budget', false)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('user_id', $user->id)
            ->whereHasMorph(
                'config',
                [TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->with([
                'config',
                'transactionType',
                'transactionItems',
                'transactionItems.category',
                'transactionItems.tags',
            ])
            ->get();

        \Log::info('Standard transactions found', ['count' => $standardTransactions->count()]);

        // Get investment transactions within date range
        $investmentTransactions = Transaction::where('schedule', false)
            ->where('budget', false)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('user_id', $user->id)
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($account) {
                    $query->where('account_id', $account->id);
                }
            )
            ->with([
                'config',
                'config.investment',
                'transactionType',
            ])
            ->get();

        // Merge and process transactions
        $transactions = $standardTransactions
            ->concat($investmentTransactions);

        \Log::info('Total transactions merged', ['count' => $transactions->count()]);

        $transactions = $transactions->map(function ($transaction) use ($account) {
                $transaction->transactionGroup = 'history';

                if ($transaction->isStandard()) {
                    $transaction->transactionOperator = $transaction->transactionType->amount_multiplier
                        ?? ($transaction->config->account_from_id === $this->currentAccount->id ? -1 : 1);
                    $transaction->account_from_name = $this->allAccounts[$transaction->config->account_from_id];
                    $transaction->account_to_name = $this->allAccounts[$transaction->config->account_to_id];
                    $transaction->amount_from = $transaction->config->amount_from;
                    $transaction->amount_to = $transaction->config->amount_to;
                    $transaction->tags = $transaction->tags()->values();
                    $transaction->categories = $transaction->categories()->values();
                } elseif ($transaction->isInvestment()) {
                    $amount = $transaction->cashflow_value ?? 0;

                    $transaction->transactionOperator = $transaction->transactionType->amount_multiplier;
                    $transaction->account_from_name = $this->allAccounts[$transaction->config->account_id];
                    $transaction->account_to_name = $transaction->config->investment->name;
                    $transaction->amount_from = ($amount < 0 ? -$amount : null);
                    $transaction->amount_to = ($amount > 0 ? $amount : null);
                    $transaction->amount = ($amount !== 0 ? $amount : null);
                    $transaction->tags = [];
                    $transaction->categories = [];
                    $transaction->quantity = $transaction->config->quantity;
                    $transaction->price = $transaction->config->price;
                    $transaction->currency = $account->config->currency;
                }

                return $transaction;
            });

        // Calculate running totals starting from opening balance
        $runningTotal = $openingBalance;

        $data = $transactions
            ->sortBy(['date', 'transactionType.amount_multiplier'])
            ->map(function ($transaction) use (&$runningTotal) {
                $runningTotal += ($transaction->transactionOperator === 1
                    ? $transaction->amount_to
                    : -1 * $transaction->amount_from);
                $transaction->running_total = $runningTotal;

                return $transaction;
            })
            ->values();

        // Check for an active balance checkpoint on the requested end date
        $checkpoint = AccountBalanceCheckpoint::active()
            ->forAccount($account->id)
            ->whereDate('checkpoint_date', $endDate)
            ->first();

        $checkpointBalance = $checkpoint ? (float) $checkpoint->balance : null;
        $checkpointVariance = $checkpoint ? round($runningTotal - $checkpointBalance, 2) : null;
        $checkpointMatches = $checkpoint ? (abs($checkpointVariance) < 0.01) : null;

        JavaScriptFacade::put([
            'currency' => $account->config->currency,
            'transactionData' => $data,
            'openingBalance' => $openingBalance,
            'closingBalance' => $runningTotal,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'checkpoint' => $checkpoint ? [
                'id' => $checkpoint->id,
                'checkpoint_date' => $checkpoint->checkpoint_date->format('Y-m-d'),
                'balance' => $checkpointBalance,
                'note' => $checkpoint->note,
                'matches' => $checkpointMatches,
            ] : null,
        ]);

        return view(
            'account.reconcile',
            [
                'account' => $account,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'openingBalance' => $openingBalance,
                'closingBalance' => $runningTotal,
                'transactionData' => $data,
                'checkpoint' => $checkpoint,
                'checkpointVariance' => $checkpointVariance,
                'checkpointMatches' => $checkpointMatches,
            ]
        );
    }

    /**
     * Calculate account balance before a specific date (not including transactions on that date).
     */
    private function calculateBalanceBeforeDate(AccountEntity $account, string $date): float
    {
        if (!$account->isAccount()) {
            return 0;
        }

        // Start with opening balance
        $balance = $account->config->opening_balance ?? 0;

        // Add all standard transactions before the date
        $standardTransactions = Transaction::where('date', '<', $date)
            ->where('schedule', false)
            ->where('budget', false)
            ->whereHasMorph(
                'config',
                [TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->with('config')
            ->get();

        foreach ($standardTransactions as $transaction) {
            $config = $transaction->config;

            // Money coming in
            if ($config->account_to_id === $account->id) {
                $balance += $config->amount_to ?? 0;
            }

            // Money going out
            if ($config->account_from_id === $account->id) {
                $balance -= $config->amount_from ?? 0;
            }
        }

        // Add all investment transactions before the date
        $investmentTransactions = Transaction::where('date', '<', $date)
            ->where('schedule', false)
            ->where('budget', false)
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($account) {
                    $query->where('account_id', $account->id);
                }
            )
            ->get();

        foreach ($investmentTransactions as $transaction) {
            $amount = $transaction->cashflow_value ?? 0;
            $balance += $amount;
        }

        return $balance;
    }
}
