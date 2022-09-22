<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class ReportController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function cashFlow(Request $request)
    {
        /**
         * @get('/reports/cashflow')
         * @name('reports.cashflow')
         * @middlewares('web', 'auth')
         */

        // Check if forecast is required
        $withForecast = $request->get('withForecast') ?? false;

        // Get axes setting
        $singleAxes = $request->get('singleAxes') ?? false;

        // Get monthly average currency rate for all currencies
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true, true);

        $firstRates = $allRates->groupBy('from_id')
            ->map(function ($group) {
                return $group->firstWhere('month', $group->min('month'));
            });

        // Get opening balance for all accounts
        $accounts = Auth::user()
        ->accounts()
        ->with([
            'config',
            'config.currency',
        ])
        ->get();

        $openingBalances = $accounts->map(function ($account) use ($firstRates, $baseCurrency) {
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
        ->where('transactions.user_id', '=', Auth::user()->id)
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
        ->where('transactions.user_id', Auth::user()->id)
        ->where('transactions.config_type', 'transaction_detail_investment')
        ->whereIn('transactions.transaction_type_id', function ($query) {
            $query->from('transaction_types')
            ->select('id')
            ->where('type', 'Investment')
            ->whereNotNull('amount_operator');
        })
        ->get();

        $transactionList = $standardTransactionsList->merge($investmentTransactionsList);

        [$transactionsHistory, $transactionSchedule] = $transactionList->partition(function ($transaction) {
            return ! $transaction->schedule && ! $transaction->budget;
        });

        // Group standard transactions by month, and get all relevant details
        $compact = [];
        $transactionsHistory->each(function ($transaction) use (&$compact, $accountCurrencyList) {
            $currency = $accountCurrencyList[$transaction->account_id];

            $compact[$transaction->month][$currency][] = floatval($transaction->amount);
        });

        if ($withForecast) {
            // Hydrate model
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
                if (! array_key_exists($month, $monthlyData)) {
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

        usort($final, function ($a, $b) {
            return $a['month'] <=> $b['month'];
        });

        JavaScriptFacade::put([
            'transactionDataHistory' => $final,
            'singleAxes' => (bool) $singleAxes,
            'currency' => $baseCurrency,
        ]);

        return view(
            'reports.cashflow',
            [
                'withForecast' => $withForecast,
                'singleAxes' => $singleAxes,
            ]
        );
    }

    public function budgetChart(Request $request)
    {
        /**
         * @get('/reports/budgetchart')
         * @name('reports.budgetchart')
         * @middlewares('web', 'auth')
         */
        // Get requested aggregation period
        $byYears = $request->get('byYears') ?? false;

        // Get all categories
        $categories = Category::all()->sortBy('full_name');

        // Pass currency related data for amCharts
        JavaScriptFacade::put([
            'currency' => $this->getBaseCurrency(),
            'categories' => $request->get('categories', []),
            'byYears' => $byYears,
        ]);

        return view(
            'reports.budgetchart',
            [
                'categories' => $categories->pluck('full_name', 'id'),
                'byYears' => $byYears,
            ]
        );
    }

    /**
     * Display form for searching transactions. Pass any preset filters from query string.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function transactionsByCriteria(Request $request)
    {
        /**
         * @get('/reports/transactions')
         * @name('reports.transactions')
         * @middlewares('web', 'auth')
         */
        // Get preset filters from query string
        $filters = [];
        if ($request->has('accounts')) {
            $filters['accounts'] = $request->get('accounts');
        }
        if ($request->has('payees')) {
            $filters['payees'] = $request->get('payees');
        }
        if ($request->has('categories')) {
            $filters['categories'] = $request->get('categories');
        }
        if ($request->has('tags')) {
            $filters['tags'] = $request->get('tags');
        }

        JavaScriptFacade::put([
            'filters' => $filters,
        ]);

        return view('reports.transactions');
    }

    public function getSchedules()
    {
        /**
         * @get('/reports/schedule')
         * @name('report.schedules')
         * @middlewares('web', 'auth')
         */
        return view('reports.schedule');
    }
}
