<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountEntity;
use App\Currency;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Log;
use JavaScript;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

class MainController extends Controller
{
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
     *
     * @param  mixed $withClosed Indicate, whether closed accounts should also be displayed
     * @return void
     */
    public function index($withClosed = null)
    {
        $accounts = AccountEntity::where('config_type', 'account')
            ->when(!$withClosed, function($query) {
                $query->where('active', '1');
            })
            ->with([
                'config',
                'config.account_group',
                'config.currency',
            ])
            ->get();
        //TODO: would this be a better approach?
        //$accounts = Account::all()->load(['config']);

        //get all currencies for rate calculation
        $baseCurrency = Currency::where('base', 1)->firstOrFail();
        $currencies = Currency::all();

        $accounts
            ->map(function($account) use ($currencies, $baseCurrency) {
                //get account group name for later grouping
                $account['account_group'] = $account->config->account_group->name;

                //get all standard transactions
                $standardTransactions = Transaction::with(
                    [
                        'config',
                        'transactionType',
                    ])
                    ->where('schedule', 0)
                    ->where('budget', 0)
                    ->whereHasMorph(
                        'config',
                        [\App\TransactionDetailStandard::class],
                        function (Builder $query) use ($account) {
                            $query->Where('account_from_id', $account->id);
                            $query->orWhere('account_to_id', $account->id);
                        }
                    )
                    ->get();

                //get all investment transactions
                $investmentTransactions = Transaction::with(
                    [
                        'config',
                        'transactionType',
                    ])
                    ->where('schedule', 0)
                    ->where('budget', 0)
                    ->whereHasMorph(
                        'config',
                        [\App\TransactionDetailInvestment::class],
                        function (Builder $query) use ($account) {
                            $query->Where('account_id', $account->id);
                        }
                    )
                    ->get();

                $transactions = $standardTransactions->merge($investmentTransactions);

                //get summary of transactions
                $account['sum'] = $transactions
                    ->sum(function ($transaction) use ($account) {
                            if ($transaction->config_type == 'transaction_detail_standard') {
                                $operator = $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus');
                                return ($operator == 'minus' ? -$transaction->config->amount_from : $transaction->config->amount_to);
                            }
                            if ($transaction->config_type == 'transaction_detail_investment') {
                                $operator = $transaction->transactionType->amount_operator;
                                if (!$operator) {
                                    return 0;
                                }
                                return ($operator == 'minus'
                                        ? - $transaction->config->price * $transaction->config->quantity
                                        : $transaction->config->dividend + $transaction->config->price * $transaction->config->quantity )
                                        - $transaction->config->commission;
                            }

                            return 0;
                        });

                //add opening balance
                $account['sum'] += $account->config->openingBalance()['amount_to'];

                //add value of investments
                $investments = $account->config->getAssociatedInvestmentsAndQuantity();
                $account['sum'] += $investments->sum(function($item) {

                    $investment = \App\Investment::find($item['investment']);
                    if ($item['quantity'] > 0) {
                        return $item['quantity'] * $investment->getLatestPrice();
                    }
                    return 0;
                });

                //$account['investment_value'] = $investmentTransactions


                //apply currency exchange, if necesary
                if ($account->config->currency_id != $baseCurrency->id) {
                    $account['sum_foreign'] = $account['sum'];
                    $account['sum'] = $account['sum'] * $currencies->find($account->config->currency_id)->rate();
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

        //dd($summary);

        return view('accounts.summary',
             [
                'summary' => array_values($summary->toArray()),
                'total' => $total,
                'baseCurrency' => $baseCurrency,
            ]);
    }

    public function account_details(Account $account, $withForecast = null)
    {
        //get account details
        $account->load('config');

        //get all accounts and payees so their name can be reused
        $accounts = \App\AccountEntity::pluck('name', 'id')->all();

        //get all tags
        $tags = \App\Tag::pluck('name','id')->all();

        //get all categories
        $categories = \App\Category::all()->pluck('full_name','id');

        //get standard transactions related to selected account
        $standardTransactions = Transaction::
            whereHasMorph(
                'config',
                [\App\TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->Where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->with([
                'config',
                //'config.accountFrom',
                //'config.accountTo',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                //'transactionItems.category',
            ])
            ->where('schedule', 0)
            ->where('budget', 0)
            //->orderBy('date')
            ->get();

        //dd($standardTransactions);

        //get all investment transactions
        $investmentTransactions = Transaction::
            whereHasMorph(
                'config',
                [\App\TransactionDetailInvestment::class],
                function (Builder $query) use ($account) {
                    $query->Where('account_id', $account->id);
                }
            )
            ->with(
                [
                    'config',
                    'transactionType',
                ])
            ->where('schedule', 0)
            ->where('budget', 0)
            ->get();

        //get standard transactions with schedule
        $schedules = Transaction::
            whereHasMorph(
                'config',
                [\App\TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->Where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->whereHas('transactionSchedule', function($q){
                $q->whereNotNull('next_date');
            })
            ->with(
                [
                    'config',
                    //'config.accountFrom',
                    //'config.accountTo',
                    'transactionType',
                    'transactionItems',
                    'transactionItems.tags',
                    //'transactionItems.category',
                    'transactionSchedule',
                ])
            ->where('schedule', 1)
            //->orderBy('date')
            ->get();

        //dd($schedules);

        $scheduleData = $schedules
            ->map(function ($transaction) use ($account, $accounts, $tags, $categories) {
                $transactionArray = $transaction->toArray();

                $itemTags = [];
                $itemCategories = [];
                foreach($transactionArray['transaction_items'] as $item) {
                    if (isset($item['tags'])) {
                        foreach($item['tags'] as $tag) {
                            $itemTags[$tag['id']] = $tags[$tag['id']];
                        };
                    }
                    if (isset($item['category_id'])) {
                        $itemCategories[$item['category_id']] = $categories[$item['category_id']];
                    }
                };

                return [
                        'id' => $transaction->id,
                        'schedule' => $transaction->transactionSchedule,
                        'next_date' => $transaction->transactionSchedule->next_date->format("Y-m-d"),
                        'transaction_name' => $transaction->transactionType->name,
                        'transaction_type' => $transaction->transactionType->type,
                        'transaction_operator' => $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus'),
                        'account_from_id' => $transaction->config->account_from_id,
                        'account_from_name' => $accounts[$transaction->config->account_from_id],
                        'account_to_id' => $transaction->config->account_to_id,
                        'account_to_name' => $accounts[$transaction->config->account_to_id],
                        'amount_from' => $transaction->config->amount_from,
                        'amount_to' => $transaction->config->amount_to,
                        'tags' => array_values($itemTags), //array_values($transaction->tags()),
                        'categories' => array_values($itemCategories), //array_values($transaction->categories()),
                        'comment' => $transaction->comment,
                    ];
            })
            ->sortBy('next_date');

        //add schedule to history items, if needeed
        $scheduledItems = [];
        if ($withForecast) {
            $scheduleData->each(function($transaction) use (&$scheduledItems) {
                //dd($transaction);
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

                foreach ($transformer->transform($rule,$constraint) as $instance) {
                    //dd($instance);
                    $transaction['date'] = $instance->getStart()->format('Y-m-d');
                    $transaction['schedule'] = true;
                    $transaction['schedule_is_first'] = $first;

                    $scheduledItems[] = $transaction;

                    $first = false;

                }

            });

        }
        //dd($scheduledItems);

        $subTotal = 0;

        //adjust data, sort transactions, create running total
        $transactionDataStandard = $standardTransactions
            ->map(function ($transaction) use ($account, $accounts, $tags, $categories) {
                $transactionArray = $transaction->toArray();

                $itemTags = [];
                $itemCategories = [];
                foreach($transactionArray['transaction_items'] as $item) {
                    if (isset($item['tags'])) {
                        foreach($item['tags'] as $tag) {
                            $itemTags[$tag['id']] = $tags[$tag['id']];
                        };
                    }
                    if (isset($item['category_id'])) {
                        $itemCategories[$item['category_id']] = $categories[$item['category_id']];
                    }
                };

                return
                    [
                        'id' => $transaction->id,
                        'date' => $transaction->date,
                        'transaction_name' => $transaction->transactionType->name,
                        'transaction_type' => $transaction->transactionType->type,
                        'transaction_operator' => $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus'),
                        'account_from_id' => $transaction->config->account_from_id,
                        'account_from_name' => $accounts[$transaction->config->account_from_id],
                        'account_to_id' => $transaction->config->account_to_id,
                        'account_to_name' => $accounts[$transaction->config->account_to_id],
                        'amount_from' => $transaction->config->amount_from,
                        'amount_to' => $transaction->config->amount_to,
                        'tags' => array_values($itemTags), //array_values($transaction->tags()),
                        'categories' => array_values($itemCategories), //array_values($transaction->categories()),
                        'reconciled' => $transaction->reconciled,
                        'comment' => $transaction->comment,
                    ];
            });

        $merged = $transactionDataStandard->merge($scheduledItems);

        $data = $merged->sortByDesc('transactionType')
            ->sortBy('date')
            //add opening item to beginning of transaction list
            ->prepend($account->openingBalance())
            ->map(function($item, $key) use (&$subTotal) {
                $subTotal += ($item['transaction_operator'] == 'plus' ? $item['amount_to']  : -$item['amount_from']);
                $item['running_total'] = $subTotal;
                return $item;
            });

        JavaScript::put([
            'transactionData' => $data,
            'scheduleData' => array_values($scheduleData->toArray()),
            'urlEditStandard' => route('transactions.editStandard', '#ID#'),
            'urlEditInvestment' => route('transactions.editInvestment', '#ID#'),
            'urlCloneStandard' => route('transactions.cloneStandard', '#ID#'),
            'urlCloneInvestment' => route('transactions.cloneInvestment', '#ID#'),
            'urlDelete' => action('TransactionController@destroy', '#ID#'),
            'urlSkip' => route('transactions.skipScheduleInstance', '#ID#'),
            'urlEnterWithEditStandard' => route('transactions.enterWithEditStandard', '#ID#'),
        ]);

        return view('accounts.history',
            [
                'account' => $account,
                'withForecast' => $withForecast,
            ]);
    }
}
