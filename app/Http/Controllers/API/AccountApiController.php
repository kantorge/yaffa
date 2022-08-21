<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getList(Request $request)
    {
        /**
         * @get('/api/assets/account')
         * @middlewares('api', 'auth:sanctum')
         */
        if ($request->get('q')) {
            $accounts = Auth::user()
                ->accounts()
                ->when($request->missing('withInactive'), function ($query) {
                    $query->active();
                })
                ->select(['id', 'name AS text'])
                ->where('name', 'LIKE', '%'.$request->get('q').'%')
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
                ->when($request->missing('withInactive'), function ($query) {
                    $query->where('account_entities.active', true);
                })
                ->where('transactions.user_id', Auth::user()->id)
                ->where('account_entities.user_id', Auth::user()->id)
                ->groupBy("transaction_details_standard.account_{$type}_id")
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->get();

            // If no results were found, fallback to blank query
            if ($accounts->count() === 0) {
                $accounts = Auth::user()
                    ->accounts()
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

    public function getStandardList(Request $request)
    {
        /**
         * @get('/api/assets/account/standard')
         * @middlewares('api', 'auth:sanctum')
         */
        if ($request->get('q')) {
            $accounts = Auth::user()
                ->accounts()
                ->active()
                ->select(['id', 'name AS text'])
                ->where('name', 'LIKE', '%'.$request->get('q').'%')
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
                ->where('transactions.user_id', Auth::user()->id)
                ->where('account_entities.user_id', Auth::user()->id)
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
                $accounts = Auth::user()
                    ->accounts()
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

    public function getAccountListForInvestments(Request $request)
    {
        /**
         * @get('/api/assets/account/investment')
         * @middlewares('api', 'auth:sanctum')
         */
        if ($request->get('q')) {
            $accounts = Auth::user()
                ->accounts()
                ->active()
                ->when($request->get('currency_id'), function ($query) use ($request) {
                    // Get account entity with config having the same currency as the one provided
                    $query->whereHasMorph(
                        'config',
                        [Account::class],
                        function (Builder $query) use ($request) {
                            $query->where('currency_id', $request->get('currency_id'));
                        }
                    );
                })
                ->select(['id', 'name AS text'])
                ->where('name', 'LIKE', '%'.$request->get('q').'%')
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
                    'transaction_details_investment.account_id'
                )
                ->select('account_entities.id', 'account_entities.name AS text')
                ->where('account_entities.active', true)
                ->where('transactions.user_id', Auth::user()->id)
                ->where('account_entities.user_id', Auth::user()->id)
                ->when($request->get('currency_id'), function ($query) use ($request) {
                    return $query
                        ->join(
                            'accounts',
                            'accounts.id',
                            '=',
                            'account_entities.config_id'
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
                ->groupBy('transaction_details_investment.account_id')
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->get();
        }

        //return data
        return response()->json($accounts, Response::HTTP_OK);
    }

    /**
     * Get the the currency associated with the account.
     *
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \App\Models\Currency
     */
    public function getAccountCurrency(AccountEntity $accountEntity): Currency
    {
        /**
         * @get('/api/assets/account/currency/{accountEntity}')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('view', $accountEntity);
        $accountEntity->load('config');

        return $accountEntity->config->currency;
    }

    /**
     * Get the account entity for the given id.
     *
     * @param  AccountEntity  $accountEntity
     * @return Response
     */
    public function getItem(AccountEntity $accountEntity)
    {
        /**
         * @get('/api/assets/account/{accountEntity}')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('view', $accountEntity);

        $accountEntity->load(['config', 'config.currency']);

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }
}
