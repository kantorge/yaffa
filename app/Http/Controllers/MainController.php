<?php

namespace App\Http\Controllers;

use App\AccountEntity;
use App\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JavaScript;

class MainController extends Controller
{
    public function index() {
        $accounts = AccountEntity::where('config_type', 'account')->get();

        $transactions = Transaction::with(
            [
                'config',
                'transactionType',
            ])->get();

        $withdrawals = $transactions->filter(function ($value, $key) {
            return $value->transactionType->name == 'withdrawal';
        });

        dd($withdrawals);

        return view('main.index');
    }

    public function account_details(AccountEntity $account) {
        $account->load('config');

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

        $openingItem = [
            'id' => null,
            'date' => null,
            'transaction_name' => 'Opening balance',
            'transaction_type' => 'Opening balance',
            'transaction_operator' => 'plus',
            'account_from_id' => null,
            'account_from_name' => null,
            'account_to_id' => null,
            'account_to_name' => null,
            'amount_from' => 0,
            'amount_to' => $account->config->opening_balance,
            'tags' => [],
            'categories' => [],
            'reconciled' => 0,
            'comment' => null,
            'edit_url' => null,
            'delete_url' => null,

        ];

        //dd($transactions);
        $subTotal = 0;

        $data = $transactions
            ->map(function ($transaction) use ($account) {
            return [
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
            ->prepend($openingItem)
            ->map(function($item, $key) use (&$subTotal) {
                $subTotal += ($item['transaction_operator'] == 'plus' ? $item['amount_to']  : -$item['amount_from']);
                $item['running_total'] = $subTotal;
                return $item;
            });

        //dd($data);

        JavaScript::put([
            'data' => $data,
        ]);

        return view('accounts.history');
    }
}
