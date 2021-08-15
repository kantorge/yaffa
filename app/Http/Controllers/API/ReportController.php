<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    /**
     * Collect actual and budgeted cost for selected categories, and return it aggregated by month.
     *
     * @param  Illuminate\Http\Request $request
     * @return Illuminate\Http\Response
     */
    public function budgetChart(Request $request)
    {
        // Get list of requested categories
        $requestedCategories = Category::findOrFail($request->get('categories'));

        // Ensure, that child categories are loaded for all parents
        $categories = collect();
        $requestedCategories->each(function ($category) use (&$categories) {
            if (is_null($category->parent_id)) {
                $children = Category::where('parent_id', '=', $category->id)->get();
                $categories = $categories->merge($children);
            }

            $categories->push($category);
        });

        $categories = $categories->unique('id');

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true);

        // Get all standard transactions with related categories
        $standardTransactions = TransactionItem::with([
            'transaction',
            'transaction.transactionType',
            'transaction.config.accountFrom.config',
            'transaction.config.accountTo.config',
        ])
        ->whereIn('category_id', $categories->pluck('id'))
        ->whereHas('transaction', function ($query) {
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

        // Group standard transactions by month, and get all relevant details
        $standardCompact = [];
        $standardTransactions->each(function ($item) use (&$standardCompact) {
            $month = $item->transaction->date->format('Y-m-01');
            $currency_id = ($item->transaction->transactionType->name === 'withdrawal' ? $item->transaction->config->accountFrom->config->currency_id : $item->transaction->config->accountTo->config->currency_id);

            $standardCompact[$month][$currency_id][] = ($item->transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $item->amount;
        });

        // Summarize items, applying currency rate
        $monthlyData = [];

        foreach ($standardCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = [
                        'actual' => 0,
                        'budget' => 0,
                    ];
                }

                $rate = $allRates
                    ->where('month', $month)
                    ->where('from_id', $currency)
                    ->first();

                $monthlyData[$month]['actual'] += array_sum($items) * ($rate ? $rate->rate : 1);
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
        ->where('budget', 1)
        ->where('config_type', 'transaction_detail_standard')
        ->get();

        // Unify currencies and calculate amounts only for given categories
        $budgetTransactions->transform(function ($transaction) use ($categories) {
            $transaction->sum = $transaction->transactionItems
                ->filter(function ($item) use ($categories) {
                    return $categories->pluck('id')->contains($item->category_id);
                })
                ->sum('amount');

            return $transaction;
        });

        // Get all instances by month
        $budgetInstances = $this->getScheduleInstances(
            $budgetTransactions,
            'start',
            (new Carbon())->addYears(50)
        );

        $budgetCompact = [];
        $budgetInstances->each(function ($transaction) use (&$budgetCompact, $baseCurrency) {
            $month = $transaction->date->format('Y-m-01');
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

            $budgetCompact[$month][$currency_id][] = ($transaction->transactionType->name === 'withdrawal' ? -1 : 1) * $transaction->sum;
        });

        foreach ($budgetCompact as $month => $monthData) {
            foreach ($monthData as $currency => $items) {
                if (!array_key_exists($month, $monthlyData)) {
                    $monthlyData[$month] = [
                        'actual' => 0,
                        'budget' => 0,
                    ];
                }

                $rate = $allRates
                    ->where('month', $month)
                    ->where('from_id', $currency)
                    ->first();

                $monthlyData[$month]['budget'] += array_sum($items) * ($rate ? $rate->rate : 1);
            }
        }

        // Transform standard data into amCharts format
        $result = [];
        foreach ($monthlyData as $key => $value) {
            $result[] = [
                'month' => $key,
                'actual' => $value['actual'],
                'budget' => $value['budget'],
            ];
        }

        // Return fetched and prepared data
        return response()->json($result, Response::HTTP_OK);
    }
}
