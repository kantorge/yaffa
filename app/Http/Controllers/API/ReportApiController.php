<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use App\Services\CategoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportApiController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    private CategoryService $categoryService;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
        $this->categoryService = new CategoryService();
    }

    /**
     * Collect actual and budgeted cost for selected categories, and return it aggregated by month.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function budgetChart(Request $request): JsonResponse
    {
        /**
         * @get('/api/budgetchart')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // Get requested aggregation period
        $byYears = $request->get('byYears') ?? false;
        $periodFormat = $byYears ? 'Y-01-01' : 'Y-m-01';

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->categoryService->getChildCategories($request);

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true, true)->sortByDesc('date_from');

        // Get all standard transactions with related categories
        $standardTransactions = TransactionItem::with([
            'transaction',
            'transaction.transactionType',
            'transaction.config.accountFrom.config',
            'transaction.config.accountTo.config',
        ])
            ->whereIn('category_id', $categories->pluck('id'))
            ->whereHas('transaction', function ($query) {
                $query->where('user_id', Auth::user()->id);
                $query->where('schedule', 0);
                $query->where('budget', 0);
                $query->where('config_type', 'transaction_detail_standard');
                $query->where(
                    'transaction_type_id',
                    '!=',
                    TransactionType::where('name', '=', 'transfer')->first()->id
                );
            })
            ->get();

        // Group standard transactions by selected period, and get all relevant details
        $standardCompact = [];
        $standardTransactions->each(function ($item) use (&$standardCompact, $periodFormat) {
            $period = $item->transaction->date->format($periodFormat);
            $currency_id = ($item->transaction->transactionType->name === 'withdrawal' ? $item->transaction->config->accountFrom->config->currency_id : $item->transaction->config->accountTo->config->currency_id);

            $standardCompact[$period][$currency_id][] = ($item->transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $item->amount;
        });

        // Summarize items, applying currency rate
        $dataByPeriod = [];

        foreach ($standardCompact as $period => $periodData) {
            foreach ($periodData as $currency => $items) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => 0,
                    ];
                }

                $rate = $allRates
                    ->where('from_id', $currency)
                    ->firstWhere('date_from', '<=', $period);

                $dataByPeriod[$period]['actual'] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        // Get all budget transactions with related categories
        $budgetTransactions = Transaction::with([
            'transactionItems',
            'transactionType',
            'transactionSchedule',
            'config.accountFrom.config',
            'config.accountTo.config',
        ])
            ->whereHas('transactionItems', function ($query) use ($categories) {
                $query->whereIn('category_id', $categories->pluck('id'));
            })
            ->where('user_id', Auth::user()->id)
            ->where('budget', 1)
            ->where('config_type', 'transaction_detail_standard')
            ->get();

        // Unify currencies and calculate amounts only for given categories
        $budgetTransactions->transform(function ($transaction) use ($categories) {
            $transaction->sum = $transaction->transactionItems
                ->filter(fn ($item) => $categories->pluck('id')->contains($item->category_id))
                ->sum('amount');

            return $transaction;
        });

        // Get all instances by month
        $budgetInstances = $this->getScheduleInstances(
            $budgetTransactions,
            'start',
            null,
            (new Carbon())->addYears(50)
        );

        $budgetCompact = [];
        $budgetInstances->each(function ($transaction) use (&$budgetCompact, $baseCurrency, $periodFormat) {
            $period = $transaction->date->format($periodFormat);
            if ($transaction->transactionType->name === 'withdrawal') {
                if ($transaction->config->accountFrom) {
                    $currency_id = $transaction->config->accountFrom->config->currency_id;
                } else {
                    $currency_id = $baseCurrency->id;
                }
            } else {
                if ($transaction->config->accountTo) {
                    $currency_id = $transaction->config->accountTo->config->currency_id;
                } else {
                    $currency_id = $baseCurrency->id;
                }
            }

            $budgetCompact[$period][$currency_id][] = ($transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $transaction->sum;
        });

        foreach ($budgetCompact as $period => $periodData) {
            foreach ($periodData as $currency => $items) {
                if (!array_key_exists($period, $dataByPeriod)) {
                    $dataByPeriod[$period] = [
                        'actual' => null,
                        'budget' => 0,
                    ];
                }

                $rate = $allRates
                    ->where('from_id', $currency)
                    ->firstWhere('date_from', '<=', $period);

                $dataByPeriod[$period]['budget'] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        // Transform standard data into amCharts format
        $result = [];
        foreach ($dataByPeriod as $key => $value) {
            $result[] = [
                'period' => new Carbon($key),
                'actual' => $value['actual'],
                'budget' => $value['budget'],
            ];
        }

        usort($result, fn ($a, $b) => $a['period'] <=> $b['period']);

        // Return fetched and prepared data
        return response()->json($result, Response::HTTP_OK);
    }

    /**
     * Collect actual transactions for the given interval.
     *
     * @param string $transactionType
     * @param string $dataType Planned feature for budget. Currently actual transactions are supported.
     * @param int $year
     * @param int|null $month
     * @return JsonResponse
     */
    public function getCategoryWaterfallData(string $transactionType, string $dataType, int $year, int $month = null): JsonResponse
    {
        /**
         * @get('/api/reports/waterfall/{type}/{year}/{month?}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true, true)->sortByDesc('date_from');

        // Final result placeholder
        $dataByCategory = [];

        if ($transactionType === 'all' || $transactionType === 'standard') {
            // Get all standard transactions with related categories
            $standardTransactions = TransactionItem::with([
                'category',
                'transaction',
                'transaction.transactionType',
                'transaction.config.accountFrom.config',
                'transaction.config.accountTo.config',
            ])
                ->whereHas('transaction', function ($query) use ($year, $month) {
                    $query->where('user_id', Auth::user()->id)
                        ->when($month === null, fn ($query) => $query->whereRaw('YEAR(date) = ?', [$year]))
                        ->when($year && $month, function ($query) use ($year, $month) {
                            return $query->whereRaw('YEAR(date) = ?', [$year])
                                ->whereRaw('MONTH(date) = ?', [$month]);
                        })
                        ->byScheduleType('none')
                        ->where('config_type', 'transaction_detail_standard')
                        ->where(
                            'transaction_type_id',
                            '!=',
                            TransactionType::where('name', '=', 'transfer')->first()->id
                        );
                })
                ->get();

            $standardTransactions->each(function ($item) use (&$dataByCategory, $baseCurrency, $allRates) {
                // Determine the category group. This should be the top level category ideally.
                $category = $item->category?->parent?->name ?? $item->category?->name ?? 'No category assigned';

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = 0;
                }

                // Get the currency and determine currency rate
                $currency_id = ($item->transaction->transactionType->name === 'withdrawal' ? $item->transaction->config->accountFrom->config->currency_id : $item->transaction->config->accountTo->config->currency_id);
                if ($currency_id !== $baseCurrency->id) {
                    $rate = $allRates
                        ->where('from_id', $currency_id)
                        ->firstWhere('date_from', '<=', $item->transaction->date);
                } else {
                    $rate = null;
                }

                $dataByCategory[$category] += ($item->transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $item->amount * ($rate ? $rate->rate : 1);
            });
        }

        if ($transactionType === 'all' || $transactionType === 'investment') {
            // Add investment transaction results
            $investmentTransactions = Transaction::with([
                'transactionType',
                'config.account.config',
            ])
                //->where('config_type', 'transaction_detail_investment')
                ->whereIn(
                    'transaction_type_id',
                    TransactionType::where('type', 'investment')->whereNotNull('amount_operator')->get()->pluck('id')
                )
                ->where('user_id', Auth::user()->id)
                ->when($month === null, fn ($query) => $query->whereRaw('YEAR(date) = ?', [$year]))
                ->when($year && $month, function ($query) use ($year, $month) {
                    return $query->whereRaw('YEAR(date) = ?', [$year])
                        ->whereRaw('MONTH(date) = ?', [$month]);
                })
                ->get();

            $investmentTransactions->each(function ($transaction) use (&$dataByCategory, $baseCurrency, $allRates) {
                // Determine the category group. This should be the top level category ideally.
                $category = ($transaction->transactionType->amount_operator === 'plus' ? 'Investment income' : 'Investment payment');

                // Ensure that we have an array element for the category
                if (!array_key_exists($category, $dataByCategory)) {
                    $dataByCategory[$category] = 0;
                }

                // Get the currency and determine currency rate
                $currency_id = $transaction->config->account->config->currency_id;
                if ($currency_id !== $baseCurrency->id) {
                    $rate = $allRates
                        ->where('from_id', $currency_id)
                        ->firstWhere('date_from', '<=', $transaction->date);
                } else {
                    $rate = null;
                }

                $dataByCategory[$category] += $transaction->cashflowValue() * ($rate ? $rate->rate : 1);
            });
        }

        $result = [];
        foreach ($dataByCategory as $category => $value) {
            $result[] = [
                'category' => $category,
                'value' => $value,
            ];
        }

        // Return fetched and prepared data
        return response()->json(
            [
                'chartData' => $result,
            ],
            Response::HTTP_OK
        );
    }

    public function getCashflowData(Request $request): JsonResponse
    {
        // Check if forecast is required
        $withForecast = $request->get('withForecast') ?? false;

        // Get monthly average currency rate for all currencies
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true, true);

        $firstRates = $allRates->groupBy('from_id')
            ->map(fn ($group) => $group->firstWhere('month', $group->min('month')));

        // Get opening balance for all accounts or requested accounts
        $accounts = $request->user()
            ->accounts()
            ->with([
                'config',
                'config.currency',
            ])
            ->get();

        $openingBalances = $accounts
            ->map(function ($account) use ($firstRates, $baseCurrency) {
                $account['sum'] += $account->config->opening_balance;

                // Apply currency exchange, if necesary
                if ($account->config->currency_id !== $baseCurrency->id) {
                    // Get first exchange rate for given currency
                    $rate = $firstRates
                        ->where('from_id', $account->config->currency_id)
                        ->first();

                    $account['sum'] *= ($rate ? $rate->rate : 1);
                }

                return $account;
            });

        // Compact accounts and currencies
        $accountCurrencyList = $accounts->pluck('config.currency_id', 'id')->toArray();

        // Get all standard transactions (one-time AND scheduled/budget)
        $transactionTypeWithdrawal = TransactionType::where('name', 'withdrawal')->first();
        $transactionTypeDeposit = TransactionType::where('name', 'deposit')->first();

        $standardTransactionsList = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.schedule',
                'transactions.budget',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
            )
            ->selectRaw('LAST_DAY(transactions.date - interval 1 month) + interval 1 day AS month')
            ->selectRaw('CASE WHEN transactions.transaction_type_id = ? THEN transaction_details_standard.account_from_id ELSE transaction_details_standard.account_to_id END AS account_id', [$transactionTypeWithdrawal->id])
            ->selectRaw('CASE WHEN transactions.transaction_type_id = ? THEN -transaction_details_standard.amount_from ELSE transaction_details_standard.amount_to END AS amount', [$transactionTypeWithdrawal->id])
            ->leftJoin('transaction_details_standard', 'transactions.config_id', '=', 'transaction_details_standard.id')
            ->leftJoin('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
            ->where('transactions.user_id', '=', $request->user()->id)
            ->where('transactions.config_type', '=', 'transaction_detail_standard')
            ->whereIn('transactions.transaction_type_id', [$transactionTypeDeposit->id, $transactionTypeWithdrawal->id])
            ->get();

        $investmentTransactionsList = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.schedule',
                'transactions.budget',
                'transaction_details_investment.account_id',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
            )
            ->selectRaw('LAST_DAY(transactions.date - interval 1 month) + interval 1 day AS month')
            ->selectRaw('(CASE WHEN transaction_types.amount_operator = "plus" THEN 1 ELSE -1 END) * (IFNULL(transaction_details_investment.price, 0) * IFNULL(transaction_details_investment.quantity, 0)) + IFNULL(transaction_details_investment.dividend, 0) - IFNULL(transaction_details_investment.tax, 0) - IFNULL(transaction_details_investment.commission, 0) AS amount')
            ->leftJoin('transaction_details_investment', 'transactions.config_id', '=', 'transaction_details_investment.id')
            ->leftJoin('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
            ->leftJoin('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->where('transactions.user_id', $request->user()->id)
            ->where('transactions.config_type', 'transaction_detail_investment')
            ->whereIn('transactions.transaction_type_id', function ($query) {
                $query->from('transaction_types')
                    ->select('id')
                    ->where('type', 'Investment')
                    ->whereNotNull('amount_operator');
            })
            ->get();

        $transactionList = $standardTransactionsList->merge($investmentTransactionsList);

        [$transactionsHistory, $transactionSchedule] = $transactionList->partition(fn ($transaction) => !$transaction->schedule && !$transaction->budget);

        // Group standard transactions by month, and get all relevant details
        $compact = [];
        $transactionsHistory->each(function ($transaction) use (&$compact, $accountCurrencyList) {
            $currency = $accountCurrencyList[$transaction->account_id];

            $compact[$transaction->month][$currency][] = floatval($transaction->amount);
        });

        if ($withForecast) {
            $transactionSchedule = $transactionSchedule->map(function ($transaction) {
                $item = [
                    'id' => $transaction->id,
                    'amount' => floatval($transaction->amount),
                    'account_id' => $transaction->account_id,
                    'transaction_schedule' => (object) [
                        'start_date' => $transaction->start_date,
                        'next_date' => $transaction->next_date,
                        'end_date' => $transaction->end_date,
                        'frequency' => $transaction->frequency,
                        'interval' => $transaction->interval,
                        'count' => $transaction->count,
                    ],
                ];

                return Transaction::hydrate([$item])[0];
            });

            // Get standard transaction schedule and/or budget instances
            $this->getScheduleInstances(
                $transactionSchedule,
                'custom',
                null,
                (new Carbon())->addYears(50)
            )->each(function ($transaction) use (&$compact, $accountCurrencyList, $baseCurrency) {
                $month = $transaction->date->format('Y-m-01');
                if (array_key_exists($transaction->account_id, $accountCurrencyList)) {
                    $currency = $accountCurrencyList[$transaction->account_id];
                } else {
                    $currency = $baseCurrency->id;
                }

                $compact[$month][$currency][] = $transaction->amount;
            });
        }

        // Summarize standard and investment items, applying currency rate
        $monthlyData = [];

        foreach ($compact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                if ($baseCurrency->id !== $currency) {
                    $rate = $allRates
                        ->where('from_id', $currency)
                        ->where('date_from', '<', new Carbon($month))
                        ->sortByDesc('date_from')
                        ->first();

                    $rate = ($rate ? $rate->rate : 1);
                } else {
                    $rate = 1;
                }

                $monthlyData[$month] += array_sum($items) * $rate;
            }
        }

        // Convert monthly data into dataTables format
        $final = [];
        $runningTotal = $openingBalances->sum('sum');
        foreach ($monthlyData as $month => $data) {
            $runningTotal += $data;
            $final[] = [
                'month' => new Carbon($month),
                'value' => $data,
                'runningTotal' => $runningTotal,
            ];
        }

        usort($final, fn ($a, $b) => $a['month'] <=> $b['month']);

        return response()->json(
            [
                'chartData' => $final,
            ],
            Response::HTTP_OK
        );
    }

    public function getAccountHistoryByMonth(AccountEntity $accountEntity, string $withForecast = 'false'): JsonResponse
    {
        $withForecast = $withForecast === 'true';

        // Get all standard transactions (one-time AND scheduled/budget)
        $transactionTypes = TransactionType::all();

        $standardTransactionsList = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.schedule',
                'transactions.budget',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
            )
            // Adjust the transaction date to the first day of the given month
            ->selectRaw('LAST_DAY(transactions.date - interval 1 month) + interval 1 day AS month')
            // Amount is based on transaction type (direction)
            ->selectRaw('CASE WHEN transactions.transaction_type_id = ? THEN -transaction_details_standard.amount_from ELSE transaction_details_standard.amount_to END AS amount', [$transactionTypes->firstWhere('name', 'withdrawal')->id])
            ->leftJoin('transaction_details_standard', 'transactions.config_id', '=', 'transaction_details_standard.id')
            ->leftJoin('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
            ->where('transactions.user_id', '=', Auth::user()->id)
            ->where('transactions.config_type', '=', 'transaction_detail_standard')
            ->where(function ($query) use ($accountEntity) {
                return $query->where('transaction_details_standard.account_from_id', $accountEntity->id)
                    ->orWhere('transaction_details_standard.account_to_id', $accountEntity->id);
            })
            ->whereIn('transactions.transaction_type_id', [$transactionTypes->firstWhere('name', 'deposit')->id, $transactionTypes->firstWhere('name', 'withdrawal')->id])
            ->get();

        $transferToTransactionsList = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.schedule',
                'transactions.budget',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
            )
            // Adjust the transaction date to the first day of the given month
            ->selectRaw('LAST_DAY(transactions.date - interval 1 month) + interval 1 day AS month')
            // Positive amount
            ->selectRaw('transaction_details_standard.amount_to AS amount')
            ->leftJoin('transaction_details_standard', 'transactions.config_id', '=', 'transaction_details_standard.id')
            ->leftJoin('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
            ->where('transactions.user_id', '=', Auth::user()->id)
            ->where('transactions.config_type', '=', 'transaction_detail_standard')
            ->where('transaction_details_standard.account_to_id', $accountEntity->id)
            ->where('transactions.transaction_type_id', $transactionTypes->firstWhere('name', 'transfer')->id)
            ->get();

        $transferFromTransactionsList = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.schedule',
                'transactions.budget',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
            )
            // Adjust the transaction date to the first day of the given month
            ->selectRaw('LAST_DAY(transactions.date - interval 1 month) + interval 1 day AS month')
            // Negative amount
            ->selectRaw('-transaction_details_standard.amount_from AS amount')
            ->leftJoin('transaction_details_standard', 'transactions.config_id', '=', 'transaction_details_standard.id')
            ->leftJoin('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
            ->where('transactions.user_id', '=', Auth::user()->id)
            ->where('transactions.config_type', '=', 'transaction_detail_standard')
            ->where('transaction_details_standard.account_from_id', $accountEntity->id)
            ->where('transactions.transaction_type_id', $transactionTypes->firstWhere('name', 'transfer')->id)
            ->get();

        $investmentTransactionsList = DB::table('transactions')
            ->select(
                'transactions.id',
                'transactions.schedule',
                'transactions.budget',
                'transaction_details_investment.account_id',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
            )
            ->selectRaw('LAST_DAY(transactions.date - interval 1 month) + interval 1 day AS month')
            ->selectRaw('(CASE WHEN transaction_types.amount_operator = "plus" THEN 1 ELSE -1 END) * (IFNULL(transaction_details_investment.price, 0) * IFNULL(transaction_details_investment.quantity, 0)) + IFNULL(transaction_details_investment.dividend, 0) - IFNULL(transaction_details_investment.tax, 0) - IFNULL(transaction_details_investment.commission, 0) AS amount')
            ->leftJoin('transaction_details_investment', 'transactions.config_id', '=', 'transaction_details_investment.id')
            ->leftJoin('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
            ->leftJoin('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->where('transactions.user_id', Auth::user()->id)
            ->where('transactions.config_type', 'transaction_detail_investment')
            ->where('transaction_details_investment.account_id', $accountEntity->id)
            ->whereIn('transactions.transaction_type_id', function ($query) {
                $query->from('transaction_types')
                    ->select('id')
                    ->where('type', 'Investment')
                    ->whereNotNull('amount_operator');
            })
            ->get();

        // Merge transactions from the two groups, but separate for historical and scheduled groups
        $transactionList = $standardTransactionsList
            ->concat($investmentTransactionsList)
            ->concat($transferToTransactionsList)
            ->concat($transferFromTransactionsList);
        [$transactionsHistory, $transactionSchedule] = $transactionList->partition(fn ($transaction) => !$transaction->schedule && !$transaction->budget);

        // Group standard transactions by month, and get all relevant details
        $compact = [];
        $transactionsHistory->each(function ($transaction) use (&$compact) {
            $compact[$transaction->month][] = floatval($transaction->amount);
        });

        if ($withForecast) {
            $transactionSchedule = $transactionSchedule->map(function ($transaction) {
                $item = [
                    'id' => $transaction->id,
                    'amount' => floatval($transaction->amount),
                    'transaction_schedule' => (object) [
                        'start_date' => $transaction->start_date,
                        'next_date' => $transaction->next_date,
                        'end_date' => $transaction->end_date,
                        'frequency' => $transaction->frequency,
                        'interval' => $transaction->interval,
                        'count' => $transaction->count,
                    ],
                ];

                return Transaction::hydrate([$item])[0];
            });

            // Get standard transaction schedule and/or budget instances
            $this->getScheduleInstances(
                $transactionSchedule,
                'custom',
                null,
                (new Carbon())->addYears(50)
            )->each(function ($transaction) use (&$compact) {
                $month = $transaction->date->format('Y-m-01');

                $compact[$month][] = $transaction->amount;
            });
        }

        // Summarize standard and investment items, applying currency rate
        $monthlyData = [];

        foreach ($compact as $month => $monthData) {
            if (!array_key_exists($month, $monthlyData)) {
                $monthlyData[$month] = 0;
            }

            $monthlyData[$month] += array_sum($monthData);
        }

        // Convert monthly data into dataTables format
        $final = [];
        $runningTotal = $accountEntity->config->opening_balance;
        foreach ($monthlyData as $month => $data) {
            $runningTotal += $data;
            $final[] = [
                'month' => new Carbon($month),
                'value' => $data,
                'runningTotal' => $runningTotal,
            ];
        }

        usort($final, fn ($a, $b) => $a['month'] <=> $b['month']);

        return response()->json(
            [
                'chartData' => $final,
            ],
            Response::HTTP_OK
        );
    }
}
