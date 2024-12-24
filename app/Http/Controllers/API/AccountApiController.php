<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountApiController extends Controller
{
    use CurrencyTrait;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function getList(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/account')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $parameters = [
            'user' => $request->user(),
            'query' => $request->get('q'),
            'limit' => (int) ($request->get('limit') ?? 10),
            'withInactive' => $request->get('withInactive', null),
            'currency_id' => $request->get('currency_id', null),
        ];

        if ($request->has('q')) {
            return response()->json(
                $this->searchAccounts($parameters),
                Response::HTTP_OK
            );
        }

        $type = ($request->get('account_type') === 'to' ? 'to' : 'from');

        $accountIds = DB::table('transactions')
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
            ->select('account_entities.id')

            // Optionally include closed accounts
            ->when($request->missing('withInactive'), function ($query) {
                $query->where('account_entities.active', true);
            })
            // Optionally limit the search for specific transaction types
            ->when($request->has('transaction_type'), function ($query) use ($request) {
                return $query->where(
                    'transaction_type_id',
                    '=',
                    config('transaction_types')[$request->get('transaction_type')]['id']
                );
            })
            // Search within account and transactions of the user
            ->where('transactions.user_id', $parameters['user']->id)
            ->where('account_entities.user_id', $parameters['user']->id)
            // Take only accounts
            ->where('account_entities.config_type', 'account')
            ->groupBy("account_entities.id")
            ->orderByRaw('count(*) DESC')
            ->when($parameters['limit'] !== 0, function ($query) use ($parameters) {
                $query->limit($parameters['limit']);
            })
            ->get()
            ->pluck('id');

        if ($accountIds->count() > 0) {
            // Hydrate models and load relation
            $accounts = AccountEntity::with(['config', 'config.accountGroup'])->findMany($accountIds);
            return response()->json($accounts, Response::HTTP_OK);
        }

        // If no results were found, fallback to blank query
        $accounts = $parameters['user']
            ->accounts()
            ->with(['config', 'config.accountGroup'])
            ->active()
            ->orderBy('name')
            ->when(!$parameters['withInactive'], function ($query) {
                $query->active();
            })
            ->when($parameters['limit'] !== 0, function ($query) use ($parameters) {
                $query->take($parameters['limit']);
            })
            ->get();

        return response()->json($accounts, Response::HTTP_OK);
    }

    private function searchAccounts(array $parameters): Collection
    {
        $parameters = array_merge(
            [
                'user' => Auth::user(),
                'query' => '',
                'limit' => 10,
                'withInactive' => false,
                'currency_id' => null,
            ],
            $parameters,
        );

        return $parameters['user']
            ->accounts()
            ->with(['config', 'config.accountGroup'])
            ->when(!$parameters['withInactive'], function ($query) {
                $query->active();
            })
            ->where('name', 'LIKE', '%' . $parameters['query'] . '%')
            ->orderBy('name')
            ->when($parameters['limit'] !== 0, function ($query) use ($parameters) {
                $query->take($parameters['limit']);
            })
            ->when($parameters['currency_id'] !== null, function ($query) use ($parameters) {
                // Get account entity with config having the same currency as the one provided
                $query->whereHasMorph(
                    'config',
                    [Account::class],
                    function (Builder $query) use ($parameters) {
                        $query->where('currency_id', $parameters['currency_id']);
                    }
                );
            })
            ->get();
    }

    public function getAccountListForInvestments(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/account/investment')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $user = $request->user();

        if ($request->get('q')) {
            $accounts = $user
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
                ->where('transactions.user_id', $user->id)
                ->where('account_entities.user_id', $user->id)
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
                    'transaction_type_id',
                    '=',
                    config('transaction_types')[$request->get('transaction_type')]['id']
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
     * Get the account entity for the given id.
     *
     * @param AccountEntity $accountEntity
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function getItem(AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @get('/api/assets/account/{accountEntity}')
         * @middlewares('api', 'auth:sanctum', 'verified')
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
     * The balance is calculated using AccountMonthlySummary, which is regularly updated.
     *
     * @param Request $request
     * @param AccountEntity|null $accountEntity
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function getAccountBalance(Request $request, AccountEntity|null $accountEntity = null): JsonResponse
    {
        /**
         * @get('/api/account/balance/{accountEntity?}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        $user = $request->user();

        // Validate the account entity and the user
        if ($accountEntity !== null && $accountEntity->user_id !== $user->id) {
            throw new AuthorizationException('You do not have permission to access this account entity.');
        }

        // Before proceeding with any calculation, check if any batch jobs are running for this user for fact data
        $batchJobsCount = DB::table('job_batches')
            ->whereIn('name', [
                'CalculateAccountMonthlySummariesJob-account_balance-fact-' . $user->id,
                'CalculateAccountMonthlySummariesJob-investment_value-fact-' . $user->id,
            ])
            ->where('finished_at', null)
            ->count();

        if ($batchJobsCount > 0) {
            return response()
                ->json(
                    [
                        'result' => 'busy',
                        'message' => __('Account summary calculations are in progress.'),
                    ],
                    Response::HTTP_OK
                );
        }

        $baseCurrency = $this->getBaseCurrency();

        // Get all currencies for rate calculation
        $currencies = $user->currencies()->get();

        // Load all accounts or the selected one
        $accounts = $user
            ->accounts()
            ->when($accountEntity, fn ($query) => $query->where('id', $accountEntity->id))
            ->with(['config', 'config.accountGroup', 'config.currency'])
            ->get()
            ->makeHidden([
                'created_at',
                'updated_at',
                'user_id',
            ]);

        // Get the calculated summary for all or the selected account
        // We need only the fact data_type, not the forecast or budget data
        // We need the sum of all standard transactions and the latest value for investments
        $standardSummary = DB::table('account_monthly_summaries')
            ->select('account_entity_id')
            ->selectRaw('sum(amount) as total_amount')
            ->where('transaction_type', 'account_balance')
            ->where('data_type', 'fact')
            ->when(
                $accountEntity !== null,
                fn ($query) => $query->where('account_entity_id', $accountEntity->id)
            )
            ->when(
                $accountEntity === null,
                fn ($query) => $query->whereIn('account_entity_id', $user->accounts()->get()->pluck('id'))
            )
            ->groupBy('account_entity_id')
            ->get();

        // For the investments, we need to determine the latest date for each account
        $latestDates = DB::table('account_monthly_summaries')
            ->select('account_entity_id')
            ->selectRaw('MAX(date) as max_date')
            ->where('transaction_type', 'investment_value')
            ->where('data_type', 'fact')
            ->where('user_id', $user->id)
            ->when(
                $accountEntity !== null,
                fn ($query) => $query->where('account_entity_id', $accountEntity->id),
            )
            ->groupBy('account_entity_id');

        $investmentSummary = DB::table('account_monthly_summaries')
            ->select('account_monthly_summaries.account_entity_id')
            ->selectRaw('sum(amount) as total_amount')
            ->where('transaction_type', 'investment_value')
            ->where('data_type', 'fact')
            ->where('account_monthly_summaries.user_id', $user->id)
            ->when(
                $accountEntity !== null,
                fn ($query) => $query->where('account_monthly_summaries.account_entity_id', $accountEntity->id)
            )
            ->joinSub($latestDates, 'latest_dates', function ($join) {
                $join->on('account_monthly_summaries.account_entity_id', '=', 'latest_dates.account_entity_id')
                    ->on('account_monthly_summaries.date', '=', 'latest_dates.max_date');
            })
            ->groupBy('account_monthly_summaries.account_entity_id')
            ->get();

        $accounts
            ->map(function ($account) use ($currencies, $baseCurrency, $standardSummary, $investmentSummary) {
                // Get the account group name for later grouping
                $account['account_group_name'] = $account->config->accountGroup->name;
                $account['account_group_id'] = $account->config->accountGroup->id;

                // Summarize the standard value and investment value for this account
                $account['cash'] = ($standardSummary->where('account_entity_id', $account->id)
                    ->first()
                    ->total_amount ?? 0) * 1;

                $account['investments'] = ($investmentSummary->where('account_entity_id', $account->id)
                    ->first()
                    ->total_amount ?? 0) * 1;

                $account['sum'] = $account['cash'] + $account['investments'];

                $account['currency'] = $account->config->currency;

                // Apply currency exchange, only if necesary
                if ($account->config->currency_id === $baseCurrency->id) {
                    return $account;
                }

                $rate = $currencies->find($account->config->currency_id)->rate() ?? 1;

                $account['sum_foreign'] = $account['sum'];
                $account['sum'] *= $rate;

                $account['cash_foreign'] = $account['cash'];
                $account['cash'] *= $rate;

                $account['investments_foreign'] = $account['investments'];
                $account['investments'] *= $rate;

                return $account;
            });

        return response()
            ->json(
                [
                    'result' => 'success',
                    'accountBalanceData' => $accounts,
                    'account' => $accountEntity,
                ],
                Response::HTTP_OK
            );
    }

    /**
     * Trigger the related job to update the monthly summary for the given account entity.
     *
     * @throws AuthorizationException
     */
    public function updateMonthlySummary(AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @put('/api/account/monthlySummary/{accountEntity}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $this->authorize('update', $accountEntity);

        // Check if the account entity is an account
        if ($accountEntity->config_type !== 'account') {
            return response()
                ->json(
                    [
                        'result' => 'error',
                        'message' => __('This account entity is not an account.'),
                    ],
                    Response::HTTP_BAD_REQUEST
                );
        }

        // TODO: prevent the job from running if there is already a job running for this account entity

        // Dispatch the job to update all types of monthly summaries using the related command
        Artisan::call('app:cache:account-monthly-summaries', [
            'accountEntityId' => $accountEntity->id,
        ]);

        return response()
            ->json(
                [
                    'result' => 'success',
                    'message' => __('The monthly summary for this account entity is being updated.'),
                ],
                Response::HTTP_OK
            );
    }
}
