<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ReportApiController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    /**
     * Collect actual and budgeted cost for selected categories, and return it aggregated by month.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function budgetChart(Request $request)
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
        $categories = $this->getChildCategories($request);

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
                if (! array_key_exists($period, $dataByPeriod)) {
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
                if (! array_key_exists($period, $dataByPeriod)) {
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

        usort($result, function ($a, $b) {
            return $a['period'] <=> $b['period'];
        });

        // Return fetched and prepared data
        return response()->json($result, Response::HTTP_OK);
    }

    /**
     * Collect actual transactions for the given interval.
     *
     * @param int  $year
     * @param int  $month
     * @return \Illuminate\Http\Response
     */
    public function getCategoryWaterfallData(string $transactionType, string $dataType, int $year, int $month = null)
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
            ->whereHas('transaction', function ($query) use($year, $month) {
                $query->where('user_id', Auth::user()->id)
                    ->when(is_null($month), function($query) use ($year) {
                    return $query->whereRaw('YEAR(date) = ?', [$year]);
                })
                ->when($year && $month, function($query) use ($year, $month) {
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
                if (! array_key_exists($category, $dataByCategory)) {
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
            ->when(is_null($month), function($query) use ($year) {
                return $query->whereRaw('YEAR(date) = ?', [$year]);
            })
            ->when($year && $month, function($query) use ($year, $month) {
                return $query->whereRaw('YEAR(date) = ?', [$year])
                            ->whereRaw('MONTH(date) = ?', [$month]);
            })
            ->get();

            $investmentTransactions->each(function ($transaction) use (&$dataByCategory, $baseCurrency, $allRates) {
                // Determine the category group. This should be the top level category ideally.
                $category = ($transaction->transactionType->amount_operator === 'plus' ? 'Investment income' : 'Investment payment');

                // Ensure that we have an array element for the category
                if (! array_key_exists($category, $dataByCategory)) {
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

    // TODO: unify with TransactionApiController
    private function getChildCategories(Request $request)
    {
        $categories = collect();

        if ($request->missing('categories')) {
            return $categories;
        }

        $requestedCategories = Auth::user()
            ->categories()
            ->whereIn('id', $request->get('categories'))
            ->get();

        $requestedCategories->each(function ($category) use (&$categories) {
            if ($category->parent_id === null) {
                $children = Auth::user()
                    ->categories()
                    ->where('parent_id', '=', $category->id)
                    ->get();
                $categories = $categories->merge($children);
            }

            $categories->push($category);
        });

        return $categories->unique('id');
    }
}
