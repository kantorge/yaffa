<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionType;
use JavaScript;

class ReportController extends Controller
{
    use CurrencyTrait;

    public function cashFlow()
    {
        // Get monthly average currency rate for all currencies
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true);

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

                    $account['sum'] *= $rate->rate;
                }

                return $account;
            });

        // Get all standard transactions (one-time AND scheduled)
        $standardTransactions = Transaction::where(
            function ($query) {
                $query->where('schedule', 1)
                    ->orWhere(function ($query) {
                        $query->where('schedule', 0);
                        $query->where('budget', 0);
                    });
            }
        )
        ->where(
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
            'config.accountFrom.config',
            'config.accountTo.config',
        ])
        //->limit(10)
        ->get();

        // Get all investment transactions
        $investmentTransactions = Transaction::where(
            function ($query) {
                $query->where('schedule', 1)
                    ->orWhere(function ($query) {
                        $query->where('schedule', 0);
                        $query->where('budget', 0);
                    });
            }
        )
        ->where(
            'config_type',
            'transaction_detail_investment'
        )
        // Load all necessary relations
        ->with([
            'config',
            'transactionType',
            'config.account.config',
        ])
        //->limit(10)
        ->get();

        // Group standard transactions by month, and get all relevant details
        $standardCompact = [];
        $standardTransactions->filter(function ($transaction) {
            return !$transaction->schedule;
        })
        ->each(function ($transaction) use (&$standardCompact) {
            $month = $transaction->date->format('Y-m-01');
            $currency_id = ($transaction->transactionType->name === 'withdrawal' ? $transaction->config->accountFrom->config->currency_id : $transaction->config->accountTo->config->currency_id);

            $standardCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
        });

        // Group investment transactions
        $investmentCompact = [];
        $investmentTransactions->filter(function ($transaction) {
            return !$transaction->schedule;
        })
        ->each(function ($transaction) use (&$investmentCompact) {
            $month = $transaction->date->format('Y-m-01');
            $currency_id = $transaction->config->account->config->currency_id;

            $investmentCompact[$month][$currency_id][] = $transaction->cashFlowValue(null);
        });

        // Summarize standard and investment items, applying currency rate
        $monthlyData = [];

        foreach ($standardCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                $rate = $allRates
                    ->where('month', $month)
                    ->where('from_id', $currency)
                    ->first();

                $monthlyData[$month] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        foreach ($investmentCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = 0;
                }

                $rate = $allRates
                    ->where('month', $month)
                    ->where('from_id', $currency)
                    ->first();

                $monthlyData[$month] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        // Convert monthly data into dataTables format
        $final = [];
        $runningTotal = $accounts->sum('sum');
        foreach ($monthlyData as $month => $data) {
            $runningTotal += $data;
            $final[] = [
                'month' => $month,
                'value' => $data,
                'runningTotal' => $runningTotal,
            ];
        }

        // TODO: Add schedule to history items, if needeed

        JavaScript::put([
            'transactionDataHistory' => $final,
        ]);

        return view(
            'reports.cashflow'
        );
    }

    public function budgetChart()
    {
        // Get all categories
        $categories = Category::all()->sortBy('full_name');

        // Pass currency related data for amCharts
        JavaScript::put([
            'currency' => $this->getBaseCurrency(),
        ]);

        return view(
            'reports.budgetchart',
            [
                'categories' => $categories->pluck('full_name', 'id')
            ]
        );
    }
}