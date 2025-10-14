<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class MainController extends Controller implements HasMiddleware
{
    use CurrencyTrait;
    use ScheduleTrait;

    private $allAccounts;

    private $currentAccount;

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    public function account_details(AccountEntity $account, $withForecast = null)
    {
        /**
         * @get('/account/history/{account}/{withForecast?}')
         * @name('account.history')
         * @middlewares('web', 'auth', 'verified')
         */
        $user = Auth::user();

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
}
