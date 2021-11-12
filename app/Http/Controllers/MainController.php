<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use JavaScript;

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
        $this->middleware('auth');
    }

    /**
     * Get the current value of all accounts.
     *
     * Loop all accounts, and calculate their current values, including:
     *  - opening balance
     *  - all standard transactions: + deposits - withdrawals +/- transactions respectively
     *  - all investment transaction monetary value: + sell - buy + dividends
     *  - latest value of all investments, based on actual volume: + buy + add - sell - removal
     *
     * Transaction types table holds information of operators to be used, except transfer, which depends on direction
     *
     * @param  mixed $withClosed Indicate, whether closed accounts should also be displayed
     * @return void
     */
    public function index($withClosed = null)
    {
        // Try to get base currency. Get user to define it, if no currencies exist.
        $baseCurrency =  $this->getBaseCurrency();
        if (! $baseCurrency) {
            $this->addMessage(
                "Please add at least one currency, that you'll use. You can set it as the default currency, which will be used in reports and summaries.",
                'info',
                'No currencies found',
                'info-circle'
            );

            return redirect()->route('currencies.create');
        }

        // Get all currencies for rate calculation
        $currencies = Auth::user()
            ->currencies()
            ->get();

        // Load all accounts to get current value
        $accounts = Auth::user()
            ->accounts()
            ->when(! $withClosed, function ($query) {
                return $query->active();
            })
            ->with(['config', 'config.account_group', 'config.currency'])
            ->get();

        $accounts
            ->map(function ($account) use ($currencies, $baseCurrency) {
                // Get account group name for later grouping
                $account['account_group'] = $account->config->account_group->name;

                // Get all standard transactions
                $standardTransactions = Transaction::with(
                    [
                        'config',
                        'transactionType',
                    ]
                )
                ->where('schedule', 0)
                ->where('budget', 0)
                ->whereHasMorph(
                    'config',
                    [TransactionDetailStandard::class],
                    function (Builder $query) use ($account) {
                        $query->Where('account_from_id', $account->id);
                        $query->orWhere('account_to_id', $account->id);
                    }
                )
                ->get();

                // Get all investment transactions
                $investmentTransactions = Transaction::with(
                    [
                        'config',
                        'transactionType',
                    ]
                )
                ->where('schedule', 0)
                ->where('budget', 0)
                ->whereHasMorph(
                    'config',
                    [TransactionDetailInvestment::class],
                    function (Builder $query) use ($account) {
                        $query->Where('account_id', $account->id);
                    }
                )
                ->get();

                $transactions = $standardTransactions->merge($investmentTransactions);

                // Get summary of transaction values
                $account['sum'] = $transactions
                    ->sum(function ($transaction) use ($account) {
                        return $transaction->cashflowValue($account);
                    });

                // Add opening balance
                $account['sum'] += $account->config->opening_balance;

                // Add value of investments
                $investments = $account->config->getAssociatedInvestmentsAndQuantity();
                $account['sum'] += $investments->sum(function ($item) {
                    $investment = Investment::find($item['investment']);
                    if ($item['quantity'] > 0) {
                        return $item['quantity'] * $investment->getLatestPrice();
                    }

                    return 0;
                });

                // Apply currency exchange, if necesary
                if ($account->config->currency_id != $baseCurrency->id) {
                    $account['sum_foreign'] = $account['sum'];
                    $account['sum'] *= $currencies->find($account->config->currency_id)->rate();
                }
                $account['currency'] = $account->config->currency;

                return $account;
            });

        //get summary by accounts
        $summary = $accounts
            ->sortBy('account_group')
            ->groupBy('account_group')
            ->map(function ($group, $key) {
                return [
                    'group' => $key,
                    'accounts' => $group->sortBy('name'),
                    'sum' => $group->sum('sum'),
                ];
            });

        $total = $summary->sum('sum');

        return view(
            'account.summary',
            [
                'summary' => array_values($summary->toArray()),
                'total' => $total,
                'baseCurrency' => $baseCurrency,
            ]
        );
    }

    public function account_details(AccountEntity $account, $withForecast = null)
    {
        // Get account details and load to class variable
        $this->currentAccount = $account->load([
            'config',
            'config.currency',
        ]);

        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::pluck('name', 'id')->all();

        // Get all tags
        $this->allTags = Tag::pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = Category::all()->pluck('full_name', 'id');

        // Get standard transactions related to selected account (one-time AND scheduled)
        $standardTransactions = Transaction::where(function ($query) {
            $query->where('schedule', 1)
                ->orWhere(function ($query) {
                    $query->where('schedule', 0);
                    $query->where('budget', 0);
                });
        })
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
                    $query->where('schedule', 0);
                    $query->where('budget', 0);
                });
        })
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

                $transaction->transactionOperator = $transaction->transactionType->amount_operator ?? ($transaction->config->account_from_id == $this->currentAccount->id ? 'minus' : 'plus');
                $transaction->account_from_name = $this->allAccounts[$transaction->config->account_from_id];
                $transaction->account_to_name = $this->allAccounts[$transaction->config->account_to_id];
                $transaction->amount_from = $transaction->config->amount_from;
                $transaction->amount_to = $transaction->config->amount_to;
                $transaction->tags = array_values($itemTags);
                $transaction->categories = array_values($itemCategories);
            } elseif ($transaction->config_type === 'transaction_detail_investment') {
                $amount = $transaction->cashflowValue(null);

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

        JavaScript::put([
            'currency' => $account->config->currency,
            'transactionData' => $data,
            'scheduleData' => $transactions
                ->filter(function ($transaction) {
                    return $transaction->transactionGroup === 'schedule';
                })->values(),
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
