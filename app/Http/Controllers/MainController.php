<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use JavaScript;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

class MainController extends Controller
{
    private $allAccounts;
    private $allTags;
    private $allCategories;
    private $currentAccount;

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
        $accounts = AccountEntity::where('config_type', 'account')
            ->when(!$withClosed, function ($query) {
                $query->where('active', '1');
            })
            ->with([
                'config',
                'config.account_group',
                'config.currency',
            ])
            ->get();

        // Get all currencies for rate calculation
        $baseCurrency = Currency::where('base', 1)->firstOrFail();
        $currencies = Currency::all();

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
                $account['sum'] += $account->config->openingBalance()['amount_to'];

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

    public function account_details(Account $account, $withForecast = null)
    {
        // Get account details and load to class variable
        $this->currentAccount = $account->load('config');

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
            ->map(function ($transaction) {
                $commonData = $this->transformDataCommon($transaction);
                $baseData = $this->transformDataStandard($transaction);
                $dateData = $this->transformDate($transaction);

                return array_merge($commonData, $baseData, $dateData);
            })
            ->merge($investmentTransactions
                ->map(function ($transaction) {
                    $commonData = $this->transformDataCommon($transaction);
                    $baseData = $this->transformDataInvestment($transaction);
                    $dateData = $this->transformDate($transaction);

                    return array_merge($commonData, $baseData, $dateData);
                }))
            ->filter(function ($transaction) {
                //TODO: can this be done earlier, at a more appropriate part of the code?
                if ($transaction['transaction_group'] != 'schedule') {
                    return true;
                }

                return !is_null($transaction['next_date']);
            });

        //add schedule to history items, if needeed
        if ($withForecast) {
            $transactions
            ->filter(function ($transaction) {
                    return $transaction['transaction_group'] == 'schedule';
            })
            ->each(function ($transaction) use (&$transactions) {
                $rule = new Rule();
                $rule->setStartDate(new Carbon($transaction['schedule']->start_date));

                if ($transaction['schedule']->end_date) {
                    $rule->setUntil(new Carbon($transaction['schedule']->end_date));
                }

                $rule->setFreq($transaction['schedule']->frequency);

                if ($transaction['schedule']->count) {
                    $rule->setCount($transaction['schedule']->count);
                }
                if ($transaction['schedule']->interval) {
                    $rule->setInterval($transaction['schedule']->interval);
                }

                $transformer = new ArrayTransformer();

                $transformerConfig = new ArrayTransformerConfig();
                $transformerConfig->setVirtualLimit(100);
                $transformerConfig->enableLastDayOfMonthFix();
                $transformer->setConfig($transformerConfig);

                $startDate = new Carbon($transaction['schedule']->next_date);
                $startDate->startOfDay();
                if (is_null($transaction['schedule']->end_date)) {
                    $endDate = new Carbon('next year');
                } else {
                    $endDate = new Carbon($transaction['schedule']->end_date);
                }
                $endDate->startOfDay();

                $constraint = new BetweenConstraint($startDate, $endDate, true);

                $first = true;

                foreach ($transformer->transform($rule, $constraint) as $instance) {
                    $newTransaction = $transaction;
                    $newTransaction['date'] = $instance->getStart()->format('Y-m-d');
                    $newTransaction['transaction_group'] = 'forecast';
                    $newTransaction['schedule_is_first'] = $first;

                    $transactions->push($newTransaction);

                    $first = false;
                }
            });
        }

        $subTotal = 0;

        $data = $transactions
            ->filter(function ($transaction) {
                return $transaction['transaction_group'] == 'history' || $transaction['transaction_group'] == 'forecast';
            })
            ->sortByDesc('transactionType')
            ->sortBy('date')
            // Add opening item to beginning of transaction list
            ->prepend($account->openingBalance())
            ->map(function ($item) use (&$subTotal) {
                $subTotal += ($item['transaction_operator'] == 'plus' ? $item['amount_to'] : -$item['amount_from']);
                $item['running_total'] = $subTotal;
                return $item;
            })
            ->values();

        JavaScript::put([
            'transactionData' => $data,
            'scheduleData' => $transactions
                ->filter(function ($transaction) {
                    return $transaction['transaction_group'] == 'schedule';
                })->values()
            ]);

        return view(
            'account.history',
            [
                'account' => $account,
                'withForecast' => $withForecast,
            ]
        );
    }

    private function transformDate(Transaction $transaction)
    {
        if ($transaction->schedule) {
            $transaction->load(['transactionSchedule']);

            return [
                'schedule' => $transaction->transactionSchedule,
                'transaction_group' => 'schedule',
                'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->format("Y-m-d") : null),
            ];
        }

        return [
            'date' => $transaction->date,
            'transaction_group' => 'history',
        ];
    }

    private function transformDataCommon(Transaction $transaction)
    {
        return
            [
                'id' => $transaction->id,
                'transaction_name' => $transaction->transactionType->name,
                'transaction_type' => $transaction->transactionType->type,

                'reconciled' => $transaction->reconciled,
                'comment' => $transaction->comment,
            ];
    }

    private function transformDataStandard(Transaction $transaction)
    {
        $transactionArray = $transaction->toArray();

        $itemTags = [];
        $itemCategories = [];
        foreach ($transactionArray['transaction_items'] as $item) {
            if (isset($item['tags'])) {
                foreach ($item['tags'] as $tag) {
                    $itemTags[$tag['id']] = $this->allTags[$tag['id']];
                }
            }
            if (isset($item['category_id'])) {
                $itemCategories[$item['category_id']] = $this->allCategories[$item['category_id']];
            }
        }

        return
            [
                'transaction_operator' => $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $this->currentAccount->id ? 'minus' : 'plus'),
                'account_from_id' => $transaction->config->account_from_id,
                'account_from_name' => $this->allAccounts[$transaction->config->account_from_id],
                'account_to_id' => $transaction->config->account_to_id,
                'account_to_name' => $this->allAccounts[$transaction->config->account_to_id],
                'amount_from' => $transaction->config->amount_from,
                'amount_to' => $transaction->config->amount_to,

                'tags' => array_values($itemTags),

                'categories' => array_values($itemCategories),
            ];
    }

    private function transformDataInvestment(Transaction $transaction)
    {
        $amount = $transaction->cashflowValue(null);

        return
        [
            'transaction_operator' => $transaction->transactionType->amount_operator,
            'quantity_operator' => $transaction->transactionType->quantity_operator,

            'account_from_id' => $transaction->config->account_id,
            'account_from_name' => $this->allAccounts[$transaction->config->account_id],
            'account_to_id' => $transaction->config->investment_id,
            'account_to_name' => $transaction->config->investment->name,
            'amount_from' => ($amount < 0 ? -$amount : null),
            'amount_to' => ($amount > 0 ? $amount : null),

            'tags' => [],

            'investment_name' => $transaction->config->investment->name,
            'quantity' => $transaction->config->quantity,
            'price' => $transaction->config->price,
        ];
    }
}
