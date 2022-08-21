<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    use CurrencyTrait;
    use ScheduleTrait;

    private $allAccounts;

    private $allTags;

    private $allCategories;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Collect actual and budgeted cost for selected categories, and return it aggregated by month.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function budgetChart(Request $request)
    {
        /**
         * @get('/api/budgetchart')
         * @middlewares('api', 'auth:sanctum')
         */
        // Get requested aggregation period
        $byYears = $request->get('byYears') ?? false;
        $periodFormat = $byYears ? 'Y-01-01' : 'Y-m-01';

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->getChildCategories($request);

        // Get monthly average currency rate for all currencies against base currency
        $baseCurrency = $this->getBaseCurrency();
        $allRates = $this->allCurrencyRatesByMonth(true, true);

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

        // Group standard transactions by selected perio, and get all relevant details
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
                    ->where('month', $period)
                    ->where('from_id', $currency)
                    ->first();

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
                    ->where('month', $period)
                    ->where('from_id', $currency)
                    ->first();

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

    // TODO: unify with TransactionApiController::getScheduledItems(), or utilize it
    public function scheduledTransactions(Request $request)
    {
        /**
         * @get('/api/scheduled_transactions')
         * @middlewares('api', 'auth:sanctum')
         */
        // Return empty response if categories are not set or empty
        if (! $request->has('categories') || ! $request->input('categories')) {
            return response()->json([], Response::HTTP_OK);
        }

        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        // Get all tags
        $this->allTags = Tag::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        // Get all categories
        $this->allCategories = Auth::user()->categories->pluck('full_name', 'id')->all();

        // Get list of requested categories
        // Ensure, that child categories are loaded for all parents
        $categories = $this->getChildCategories($request);

        // Get all standard transactions
        $standardTransactions = Transaction::with(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionType',
                'transactionSchedule',
                'transactionItems',
                'transactionItems.tags',
            ]
        )
        ->where('user_id', Auth::user()->id)
        ->where(function ($query) {
            return $query->where('schedule', 1)
                ->orWhere('budget', 1);
        })
        ->where(
            'config_type',
            '=',
            'transaction_detail_standard'
        )
        ->whereHas('transactionItems', function ($query) use ($categories) {
            $query->whereIn('category_id', $categories->pluck('id'));
        })
        ->get();

        // Prepare data for datatables
        $transactions = $standardTransactions
            ->map(function ($transaction) {
                $itemTags = [];
                $itemCategories = [];
                foreach ($transaction->transactionItems as $item) {
                    if (isset($item['tags'])) {
                        foreach ($item['tags'] as $tag) {
                            $itemTags[$tag['id']] = $this->allTags[$tag['id']];
                        }
                    }
                    if (isset($item['category_id'])) {
                        $itemCategories[$item['category_id']] = $this->allCategories[$item['category_id']];
                    }
                }

                $transaction->account_from_name = $this->allAccounts[$transaction->config->account_from_id] ?? null;
                $transaction->account_to_name = $this->allAccounts[$transaction->config->account_to_id] ?? null;
                $transaction->amount_from = $transaction->config->amount_from;
                $transaction->amount_to = $transaction->config->amount_to;
                $transaction->tags = array_values($itemTags);
                $transaction->categories = array_values($itemCategories);

                return $transaction;
            });

        // Return fetched and prepared data
        return response()->json($transactions, Response::HTTP_OK);
    }

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
