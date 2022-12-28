<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class MainController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    private $allAccounts;

    private $allTags;

    private $allCategories;

    private $currentAccount;

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
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

        // Get all tags
        $this->allTags = $user->tags->pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = $user->categories->pluck('full_name', 'id')->all();

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
                $query->Where('account_from_id', $account->id);
                $query->orWhere('account_to_id', $account->id);
            }
        )
        ->with([
            'config',
            'transactionType',
            'transactionItems',
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
                $query->Where('account_id', $account->id);
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
        ->merge($investmentTransactions)
        // Add custom and pre-calculated attributes
        ->map(function ($transaction) {
            if ($transaction->schedule) {
                $transaction->load(['transactionSchedule']);

                $transaction->transactionGroup = 'schedule';
            } else {
                $transaction->transactionGroup = 'history';
            }

            if ($transaction->config_type === 'transaction_detail_standard') {
                $itemTags = [];
                $itemCategories = [];
                foreach ($transaction->transactionItems as $item) {
                    if (isset($item['tags'])) {
                        foreach ($item['tags'] as $tag) {
                            $itemTags[$tag['id']] = $this->allTags[$tag['id']];
                        }
                    }
                    if (isset($item['category_id'])) {
                        $itemCategories[$item['category_id']] = $this->allCategories[$item['category_id']];
                    }
                }

                $transaction->transactionOperator = $transaction->transactionType->amount_operator ?? ($transaction->config->account_from_id === $this->currentAccount->id ? 'minus' : 'plus');
                $transaction->account_from_name = $this->allAccounts[$transaction->config->account_from_id];
                $transaction->account_to_name = $this->allAccounts[$transaction->config->account_to_id];
                $transaction->amount_from = $transaction->config->amount_from;
                $transaction->amount_to = $transaction->config->amount_to;
                $transaction->tags = array_values($itemTags);
                $transaction->categories = array_values($itemCategories);
            } elseif ($transaction->config_type === 'transaction_detail_investment') {
                $amount = $transaction->cashflowValue();

                $transaction->transactionOperator = $transaction->transactionType->amount_operator;
                $transaction->quantityOperator = $transaction->transactionType->quantity_operator;
                $transaction->account_from_name = $this->allAccounts[$transaction->config->account_id];
                $transaction->account_to_name = $transaction->config->investment->name;
                $transaction->amount_from = ($amount < 0 ? -$amount : null);
                $transaction->amount_to = ($amount > 0 ? $amount : null);
                $transaction->tags = [];
                $transaction->categories = [];
                $transaction->quantity = $transaction->config->quantity;
                $transaction->price = $transaction->config->price;
            }

            return $transaction;
        })
        // Drop scheduled transactions, which are not active (next date is empty)
        ->filter(function ($transaction) {
            if (! $transaction->schedule) {
                return true;
            }

            return ! is_null($transaction->transactionSchedule->next_date);
        });

        // Add schedule to history items, if needeed
        if ($withForecast) {
            $transactions = $transactions->concat(
                $this->getScheduleInstances(
                    $transactions
                    ->filter(function ($transaction) {
                        return $transaction->schedule;
                    }),
                    'next',
                )
            );
        }

        // Final ordering and running total calculation
        $subTotal = 0;

        $data = $transactions
            ->filter(function ($transaction) {
                return $transaction->transactionGroup === 'history' || $transaction->transactionGroup === 'forecast';
            })
            ->sortByDesc('transactionType')
            ->sortBy('date')
            // Add opening item to beginning of transaction list
            ->prepend($account->config->openingBalance())
            ->map(function ($transaction) use (&$subTotal) {
                $subTotal += ($transaction->transactionOperator === 'plus' ? $transaction->amount_to : -$transaction->amount_from);
                $transaction->running_total = $subTotal;

                return $transaction;
            })
            ->values();

        JavaScriptFacade::put([
            'currency' => $account->config->currency,
            'transactionData' => $data,
            'scheduleData' => $transactions
                ->filter(function ($transaction) {
                    return $transaction->transactionGroup === 'schedule';
                })
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
