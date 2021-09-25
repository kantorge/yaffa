<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AccountApiController extends Controller
{
    public function __construct(AccountEntity $account)
    {
        $this->account = $account->where('config_type', 'account');
    }

    public function getStandardList(Request $request)
    {
        if ($request->get('q')) {
            $accounts = $this->account
                ->select(['id', 'name AS text'])
                ->where('name', 'LIKE', '%' . $request->get('q') . '%')
                ->active()
                ->orderBy('name')
                ->take(10)
                ->get();
        } else {
            $type = ($request->get('account_type') === 'to' ? 'to' : 'from');

            $accounts = DB::table('transactions')
                ->join(
                    'transaction_details_standard',
                    'transaction_details_standard.id',
                    '=',
                    'transactions.config_id'
                )
                ->join(
                    'account_entities',
                    'account_entities.id',
                    '=',
                    "transaction_details_standard.account_{$type}_id"
                )
                ->select('account_entities.id', 'account_entities.name AS text')
                ->where('account_entities.active', true)
                ->where(
                    'transaction_type_id',
                    '=',
                    TransactionType::where('name', '=', $request->get('transaction_type'))->first()->id
                )
                ->groupBy("transaction_details_standard.account_{$type}_id")
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->get();

            // If no results were found, fallback to blank query
            if ($accounts->count() === 0) {
                $accounts = $this->account
                    ->select(['id', 'name AS text'])
                    ->active()
                    ->orderBy('name')
                    ->take(10)
                    ->get();
            }
        }

        // Return data
        return response()->json($accounts, Response::HTTP_OK);
    }

    public function getInvestmentList(Request $request)
    {
        if ($request->get('q')) {
            $accounts = $this->account
                ->select(['id', 'name AS text'])
                ->where('name', 'LIKE', '%' . $request->get('q') . '%')
                ->where('active', true)
                ->orderBy('name')
                ->take(10)
                ->get();
        } else {
            $accounts = DB::table('transactions')
                ->join(
                    'transaction_details_investment',
                    'transaction_details_investment.id',
                    '=',
                    'transactions.config_id'
                )
                ->join(
                    'account_entities',
                    'account_entities.id',
                    '=',
                    "transaction_details_investment.account_id"
                )
                ->select('account_entities.id', 'account_entities.name AS text')
                ->where('account_entities.active', true)
                ->when($request->get('currency_id'), function ($query) use ($request) {
                    return $query
                        ->join(
                            'accounts',
                            'accounts.id',
                            '=',
                            "account_entities.config_id"
                        )->where(
                            'accounts.currency_id',
                            '=',
                            $request->get('currency_id')
                        );
                })
                ->where(
                    // TODO: fallback to query without this, if no results are found
                    // https://stackoverflow.com/questions/26160155/laravel-eloquent-change-query-if-no-results
                    'transaction_type_id',
                    '=',
                    TransactionType::where('name', '=', $request->get('transaction_type'))->first()->id
                )
                ->groupBy("transaction_details_investment.account_id")
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->get();
        }

        //return data
        return response()->json($accounts, Response::HTTP_OK);
    }

    public function getAccountCurrencyLabel(Account $account)
    {
		return $account->currency->suffix;
    }

    public function getItem(AccountEntity $account)
    {
        $account->load(['config', 'config.currency']);

        return response()
            ->json(
                $account,
                Response::HTTP_OK
            );
    }
}
