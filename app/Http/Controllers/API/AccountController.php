<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    use CurrencyTrait;

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
                ->where('name', 'LIKE', '%' . $request->get('q') . '%')
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
                ->where('name', 'LIKE', '%' . $request->get('q') . '%')
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

    /**
     * Get the current balance of a selected account or all accounts
     *
     * Loop all accounts, and calculate their current values, including:
     *  - opening balance
     *  - all standard transactions: + deposits - withdrawals +/- transactions respectively
     *  - all investment transaction monetary value: + sell - buy + dividends
     *  - latest value of all investments, based on actual volume: + buy + add - sell - removal
     *
     * Transaction types table holds information of operators to be used, except transfer, which depends on direction
     *
     * @param  AccountEntity  $accountEntity
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccountBalance(AccountEntity $accountEntity = null): JsonResponse
    {
        /**
         * @get('/api/account/balance/{accountEntity?})
         * @middlewares('api', 'auth:sanctum')
         */

        $baseCurrency = $this->getBaseCurrency();

        // Get all currencies for rate calculation
        $currencies = Auth::user()
            ->currencies()
            ->get();

        // Load all accounts or the selected ones get current value
        $accounts = Auth::user()
            ->accounts()
            ->when($accountEntity, function ($query) use ($accountEntity) {
                return $query->findOrFail($accountEntity);
            })
            ->with(['config', 'config.accountGroup', 'config.currency'])
            ->get()
            ->makeHidden([
                'created_at',
                'updated_at',
                'user_id',
            ]);

        $transactionTypeTransfer = TransactionType::where('name', 'transfer')->first();

        $accounts
            ->map(function ($account) use ($currencies, $baseCurrency, $transactionTypeTransfer) {
                // Get account group name for later grouping
                $account['account_group'] = $account->config->accountGroup->name;
                $account['account_group_id'] = $account->config->accountGroup->id;  //TODO: should we pass the entire object instead?

                // Get all standard transfer transactions
                $standardTransactions = Transaction::with(
                    [
                        'config',
                        'transactionType',
                    ]
                )
                    ->byScheduleType('none')
                    ->where('transaction_type_id', '=', $transactionTypeTransfer->id)
                    ->whereHasMorph(
                        'config',
                        [TransactionDetailStandard::class],
                        function (Builder $query) use ($account) {
                            $query->Where('account_from_id', $account->id);
                            $query->orWhere('account_to_id', $account->id);
                        }
                    )
                    ->get();

                // Get summary for all standard transaction (withdrawal / deposit)
                $transactionsWithdrawalValue = DB::table('transactions')
                    ->select(
                        DB::raw('sum(-transaction_details_standard.amount_from) AS amount')
                    )
                    ->leftJoin('transaction_details_standard', 'transactions.config_id', '=', 'transaction_details_standard.id')
                    ->where(function ($query) {
                        $this->commonFilters($query);
                    })
                    ->where('transactions.config_type', 'transaction_detail_standard')
                    ->whereIn('transactions.transaction_type_id', function ($query) {
                        $query->from('transaction_types')
                            ->select('id')
                            ->where('type', 'Standard')
                            ->where('name', 'withdrawal');
                    })
                    ->where('transaction_details_standard.account_from_id', $account->id)
                    ->get()
                    ->first();

                $transactionsDepositValue = DB::table('transactions')
                    ->select(
                        DB::raw('sum(transaction_details_standard.amount_to) AS amount')
                    )
                    ->leftJoin('transaction_details_standard', 'transactions.config_id', '=', 'transaction_details_standard.id')
                    ->where(function ($query) {
                        $this->commonFilters($query);
                    })
                    ->where('transactions.config_type', 'transaction_detail_standard')
                    ->whereIn('transactions.transaction_type_id', function ($query) {
                        $query->from('transaction_types')
                            ->select('id')
                            ->where('type', 'Standard')
                            ->where('name', 'deposit');
                    })
                    ->where('transaction_details_standard.account_to_id', $account->id)
                    ->get()
                    ->first();

                // Get summary for all investment transactions
                $investmentTransactionsValue = DB::table('transactions')
                    ->select(
                        DB::raw('sum(
                                    (CASE WHEN transaction_types.amount_operator = "plus" THEN 1 ELSE -1 END)
                                  * (IFNULL(transaction_details_investment.price, 0) * IFNULL(transaction_details_investment.quantity, 0))

                                  + IFNULL(transaction_details_investment.dividend, 0)
                                  - IFNULL(transaction_details_investment.tax, 0)
                                  - IFNULL(transaction_details_investment.commission, 0)
                                  ) AS amount')
                    )
                    ->leftJoin('transaction_details_investment', 'transactions.config_id', '=', 'transaction_details_investment.id')
                    ->leftJoin('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
                    ->where(function ($query) {
                        $this->commonFilters($query);
                    })
                    ->where('transactions.config_type', 'transaction_detail_investment')
                    ->whereIn('transactions.transaction_type_id', function ($query) {
                        $query->from('transaction_types')
                            ->select('id')
                            ->where('type', 'Investment')
                            ->whereNotNull('amount_operator');
                    })
                    ->where('transaction_details_investment.account_id', $account->id)
                    ->get()
                    ->first();

                // Get summary of transfer transaction values
                $account['sum'] = $standardTransactions
                    ->sum(function ($transaction) use ($account) {
                        return $transaction->cashflowValue($account);
                    });

                // Add standard transaction result
                $account['sum'] += $transactionsWithdrawalValue->amount ?? 0;
                $account['sum'] += $transactionsDepositValue->amount ?? 0;

                // Add investment transaction result
                $account['sum'] += $investmentTransactionsValue->amount ?? 0;

                // Add opening balance
                $account['sum'] += $account->config->opening_balance;

                // Add value of investments
                $investments = $account->config->getAssociatedInvestmentsAndQuantity();
                $account['sum'] += $investments->sum(function ($item) {
                    if ($item->quantity === 0) {
                        return 0;
                    }

                    $investment = Investment::find($item->investment_id);

                    return $item->quantity * $investment->getLatestPrice();
                });

                // Apply currency exchange, if necesary
                if ($account->config->currency_id !== $baseCurrency->id) {
                    $account['sum_foreign'] = $account['sum'];
                    $account['sum'] *= $currencies->find($account->config->currency_id)->rate();
                }
                $account['currency'] = $account->config->currency;

                return $account;
            });

        return response()
            ->json(
                [
                    'accountBalanceData' => $accounts,
                    'account' => $accountEntity,
                ],
                Response::HTTP_OK
            );
    }

    private function commonFilters($query)
    {
        $query->where('transactions.user_id', Auth::user()->id)
        ->where('transactions.schedule', 0)
        ->where('transactions.budget', 0);
    }
}