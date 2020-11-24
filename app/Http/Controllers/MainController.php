<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountEntity;
use App\Currency;
use App\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JavaScript;

class MainController extends Controller
{
    public function index($withClosed = null) {
        $accounts = AccountEntity::where('config_type', 'account')
            ->when(!$withClosed, function($query) {
                $query->where('active', '1');
            })
            ->get()
            ->load([
                'config',
                'config.account_group',
                'config.currency',
            ]);
        //TODO: would this be a better approach?
        //$accounts = Account::all()->load(['config']);

        //get all currencies for rate calculation
        $baseCurrency = Currency::where('base', 1)->firstOrFail();
        $currencies = Currency::all();

        $accounts->map(function($account) use ($currencies, $baseCurrency) {
            //get all standard transactions
            $transactions = Transaction::with(
                [
                    'config',
                    'transactionType',
                ])
                ->where('schedule', 0)
                ->where('budget', 0)
                //TODO: filter for standard transactions
                ->whereHasMorph(
                    'config',
                    [\App\TransactionDetailStandard::class],
                    function (Builder $query) use ($account) {
                        $query->Where('account_from_id', $account->id);
                        $query->orWhere('account_to_id', $account->id);
                    }
                )
                ->get();

            //get account group name for later grouping
            $account['account_group'] = $account->config->account_group->name;

            //get summary of transactions
            $account['sum'] = $transactions
                ->sum(function ($transaction) use ($account) {
                        $operator = $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus');
                        return ($operator == 'minus' ? -$transaction->config->amount_from : $transaction->config->amount_to);
                    });

            //apply currency exchange, if necesary
            if ($account->config->currency_id != $baseCurrency->id) {
                $account['sum_foreign'] = $account['sum'];
                $account['sum'] = $account['sum'] * $currencies->find($account->config->currency_id)->rate();
            }

            return $account;
        });

        $summary = $accounts
            ->groupBy('account_group')
            ->map(function ($group, $key) {
                return [
                    'group' => $key,
                    'accounts' => $group,
                    'sum' => $group->sum('sum')
                ];
            });

        $total = $summary->sum('sum');

        return view('accounts.summary',
             [
                'summary' => array_values($summary->toArray()),
                'total' => $total,
            ]);
    }

    public function account_details(Account $account, $withForecast = null) {
        //get account details
        $account->load('config');

        //get standard transactions related to selected account
        $transactions = Transaction::with(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ])
            ->where('schedule', 0)
            ->where('budget', 0)
            //TODO: filter for standard transactions
            ->whereHasMorph(
                'config',
                [\App\TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->Where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->orderBy('date')
            ->get();

        //get standard transactions with schedule
        $schedules = Transaction::with(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
                'transactionSchedule',
            ])
            ->where('schedule', 1)
            //TODO: filter for standard transactions
            ->whereHasMorph(
                'config',
                [\App\TransactionDetailStandard::class],
                function (Builder $query) use ($account) {
                    $query->Where('account_from_id', $account->id);
                    $query->orWhere('account_to_id', $account->id);
                }
            )
            ->orderBy('date')
            ->get();

        //dd($schedules);

        $subTotal = 0;

        //adjust data, sort transactions, create running total
        $transactionData = $transactions
            ->map(function ($transaction) use ($account) {
                return
                    [
                        'id' => $transaction->id,
                        'date' => $transaction->date,
                        'transaction_name' => $transaction->transactionType->name,
                        'transaction_type' => $transaction->transactionType->type,
                        'transaction_operator' => $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus'),
                        'account_from_id' => $transaction->config->account_from_id,
                        'account_from_name' => $transaction->config->accountFrom->name,
                        'account_to_id' => $transaction->config->account_to_id,
                        'account_to_name' => $transaction->config->accountTo->name,
                        'amount_from' => $transaction->config->amount_from,
                        'amount_to' => $transaction->config->amount_to,
                        'tags' => array_values($transaction->tags()),
                        'categories' => array_values($transaction->categories()),
                        'reconciled' => $transaction->reconciled,
                        'comment' => $transaction->comment,
                        'edit_url' => route('transactions.editStandard', $transaction->id),
                        'delete_url' => route('transactions.destroy', $transaction->id),
                    ];
            })
            ->sortByDesc('transactionType')
            ->sortBy('date')
            //add opening item to beginning of transaction list
            ->prepend($account->openingBalance())
            ->map(function($item, $key) use (&$subTotal) {
                $subTotal += ($item['transaction_operator'] == 'plus' ? $item['amount_to']  : -$item['amount_from']);
                $item['running_total'] = $subTotal;
                return $item;
            });

        $scheduleData = $schedules
            ->map(function ($transaction) use ($account) {
            return [
                        'id' => $transaction->id,
                        'next_date' => $transaction->transactionSchedule->next_date,
                        'transaction_name' => $transaction->transactionType->name,
                        'transaction_type' => $transaction->transactionType->type,
                        'transaction_operator' => $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus'),
                        'account_from_id' => $transaction->config->account_from_id,
                        'account_from_name' => $transaction->config->accountFrom->name,
                        'account_to_id' => $transaction->config->account_to_id,
                        'account_to_name' => $transaction->config->accountTo->name,
                        'amount_from' => $transaction->config->amount_from,
                        'amount_to' => $transaction->config->amount_to,
                        'tags' => array_values($transaction->tags()),
                        'categories' => array_values($transaction->categories()),
                        'comment' => $transaction->comment,
                        'edit_url' => route('transactions.editStandard', $transaction->id),
                        'delete_url' => route('transactions.destroy', $transaction->id),
                    ];
                })
            ->sortBy('next_date');

        //dd($data);

        JavaScript::put([
            'transactionData' => $transactionData,
            'scheduleData' => $scheduleData,
        ]);

        return view('accounts.history',
            [
                'account' => $account,
                'withForecast' => $withForecast,
            ]);
    }
}
