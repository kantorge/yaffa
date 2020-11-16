<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountEntity;
use App\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JavaScript;

class MainController extends Controller
{
    public function index() {
        $accounts = AccountEntity::where('config_type', 'account')->get()->load(['config', 'config.account_group']);
        //$accounts = Account::all()->load(['config']);

        $accounts->map(function($account) {
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

            $account['account_group'] = $account->config->account_group->name;

            $account['sum'] = $transactions
            ->map(function ($transaction) use ($account) {
                return [
                        'transaction_name' => $transaction->transactionType->name,
                        'transaction_type' => $transaction->transactionType->type,
                        'transaction_operator' => $transaction->transactionType->amount_operator ?? ( $transaction->config->account_from_id == $account->id ? 'minus' : 'plus'),
                        'amount_from' => $transaction->config->amount_from,
                        'amount_to' => $transaction->config->amount_to,
                    ];
                })
            ->sum(function ($transaction) {
                    return ($transaction['transaction_operator'] == 'minus' ? -$transaction['amount_from'] : $transaction['amount_to']);
                });

            return $account;
        });

        $summary = $accounts
            ->groupBy('account_group');

        return view('accounts.summary', ['summary' => $summary]);
    }

    public function account_details(Account $account) {
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
                        'edit_url' => route('transactions.edit', $transaction->id),
                        'delete_url' => action('TransactionController@destroy', $transaction->id),
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
                        'edit_url' => route('transactions.edit', $transaction->id),
                        'delete_url' => action('TransactionController@destroy', $transaction->id),
                    ];
                })
            ->sortBy('next_date');

        //dd($data);

        JavaScript::put([
            'transactionData' => $transactionData,
            'scheduleData' => $scheduleData,
        ]);

        return view('accounts.history');
    }
}
