<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use JavaScript;

class ReportController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    public function cashFlow(Request $request)
    {
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
        $accounts = AccountEntity::where('config_type', 'account')
            ->with([
                'config',
                'config.currency',
            ])
            ->get()
            ->map(function ($account) use ($firstRates, $baseCurrency) {
                $account['sum'] += $account->config->opening_balance;

                // Apply currency exchange, if necesary
                if ($account->config->currency_id != $baseCurrency->id) {
                    // Get first exchange rate for given currency
                    $rate = $firstRates
                        ->where('from_id', $account->config->currency_id)
                        ->first();

                    $account['sum'] *= ($rate ? $rate->rate : 1);
                }

                return $account;
            });

        // Get all standard transactions (one-time AND scheduled/budget)
        $standardTransactionsAll = Transaction::where(
            'config_type',
            'transaction_detail_standard'
        )
        // Exclude transfers
        ->where(
            'transaction_type_id',
            '!=',
            TransactionType::where('name', '=', 'transfer')->first()->id
        )
        // Load all necessary relations
        ->with([
            'config',
            'transactionType',
            'transactionSchedule',
            'config.accountFrom.config',
            'config.accountTo.config',
        ])
        //->orderBy('id', 'desc')
        //->limit(10)
        ->get();

        // Get all investment transactions
        $investmentTransactionsAll = Transaction::where(
            'config_type',
            'transaction_detail_investment'
        )
        // Load all necessary relations
        ->with([
            'config',
            'transactionType',
            'transactionSchedule',
            'config.account.config',
        ])
        ->get();

        [$standardTransactionsHistory, $standardTransactionSchedule] = $standardTransactionsAll->partition(function ($transaction) {
            return !$transaction->schedule && !$transaction->budget;
        });

        [$investmentTransactionsHistory, $investmentTransactionSchedule] = $investmentTransactionsAll->partition(function ($transaction) {
            return !$transaction->schedule && !$transaction->budget;
        });

        // Group standard transactions by month, and get all relevant details
        $standardCompact = [];
        $standardTransactionsHistory->each(function ($transaction) use (&$standardCompact) {
            $month = $transaction->date->format('Y-m-01');
            $currency_id = ($transaction->transactionType->name === 'withdrawal' ? $transaction->config->accountFrom->config->currency_id : $transaction->config->accountTo->config->currency_id);

            $standardCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
        });

        // Group investment transactions
        $investmentCompact = [];
        $investmentTransactionsHistory->each(function ($transaction) use (&$investmentCompact) {
            $month = $transaction->date->format('Y-m-01');
            $currency_id = $transaction->config->account->config->currency_id;

            $investmentCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
        });

        if ($withForecast) {
            // Get standard transaction schedule and/or budget instances
            $this->getScheduleInstances(
                $standardTransactionSchedule,
                'custom',
                null,
                (new Carbon())->addYears(50)
            )->each(function ($transaction) use (&$standardCompact, $baseCurrency) {
                $month = $transaction->date->format('Y-m-01');

                // Set currency to base currency, and check if any adjustments are necessary
                $currency_id = $baseCurrency->id;
                if ($transaction->transactionType->name === 'withdrawal' && $transaction->config->accountFrom) {
                    $currency_id = $transaction->config->accountFrom->config->currency_id;
                } elseif ($transaction->config->accountTo) {
                    $currency_id = $transaction->config->accountTo->config->currency_id;
                }

                $standardCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
            });

            // Get investment transaction schedule and/or budget instances
            $this->getScheduleInstances(
                $investmentTransactionSchedule,
                'start',
                null,
                (new Carbon())->addYears(50)
            )->each(function ($transaction) use (&$investmentCompact) {
                $month = $transaction->date->format('Y-m-01');
                $currency_id = $transaction->config->account->config->currency_id;

                $investmentCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
            });
        }

        // Summarize standard and investment items, applying currency rate
        $monthlyData = [];

        foreach ($standardCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                if ($baseCurrency->id != $currency) {
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

        foreach ($investmentCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                if ($baseCurrency->id != $currency) {
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
        $runningTotal = $accounts->sum('sum');
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

        JavaScript::put([
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
        // Get requested aggregation period
        $byYears = $request->get('byYears') ?? false;

        // Get all categories
        $categories = Category::all()->sortBy('full_name');

        // Pass currency related data for amCharts
        JavaScript::put([
            'currency' => $this->getBaseCurrency(),
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
}
