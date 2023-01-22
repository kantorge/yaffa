<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

class InvestmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(Investment::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        /**
         * @get('/investment')
         * @name('investment.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\Investment')
         */
        // Get all investments of the user from the database and return to view
        $investments = Auth::user()
            ->investments()
            ->with([
                'currency',
                'investmentGroup',
            ])
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
            'investments' => $investments,
        ]);

        return view('investment.index');
    }

    /**
     * Display form to edit the resource.
     *
     * @param Investment $investment
     * @return View
     */
    public function edit(Investment $investment): View
    {
        /**
         * @get('/investment/{investment}/edit')
         * @name('investment.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,investment')
         */
        return view(
            'investment.form',
            [
                'investment' => $investment,
            ]
        );
    }

    public function update(InvestmentRequest $request, Investment $investment)
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/investment/{investment}')
         * @name('investment.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,investment')
         */
        // Retrieve the validated input data
        $validated = $request->validated();
        $investment->fill($validated);
        $investment->save();

        self::addSimpleSuccessMessage(__('Investment updated'));

        return redirect()->route('investment.index');
    }

    /**
     * Display form to create new resource.
     *
     * @return View
     */
    public function create(): View
    {
        /**
         * @get('/investment/create')
         * @name('investment.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Investment')
         */
        return view('investment.form');
    }

    public function store(InvestmentRequest $request)
    {
        /**
         * @post('/investment')
         * @name('investment.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Investment')
         */
        Investment::create($request->validated());

        self::addSimpleSuccessMessage(__('Investment added'));

        return redirect()->route('investment.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Investment $investment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Investment $investment): \Illuminate\Http\RedirectResponse
    {
        /**
         * @delete('/investment/{investment}')
         * @name('investment.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,investment')
         */
        $investment->delete();

        self::addSimpleSuccessMessage(__('Investment deleted'));

        return redirect()->route('investment.index');
    }

    public function summary()
    {
        /**
         * @get('/investment/summary')
         * @name('investment.summary')
         * @middlewares('web', 'auth', 'verified')
         */
        // Show all investments from the database and return to view
        $investments = Auth::user()
            ->investments()
            ->with([
                'currency',
                'investmentGroup',
            ])
            ->get();

        $investments->map(function ($investment) {
            $investment['price'] = $investment->getLatestPrice();
            $investment['quantity'] = $investment->getCurrentQuantity();

            return $investment;
        });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'investments' => $investments,
        ]);

        return view('investment.summary');
    }

    public function show(Investment $investment)
    {
        /**
         * @get('/investment/{investment}')
         * @name('investment.show')
         * @middlewares('web', 'auth', 'verified', 'can:view,investment')
         */
        // Get all stored price points
        $prices = InvestmentPrice::where('investment_id', $investment->id)
            ->orderBy('date')
            ->get();

        // Eager load investment details to be displayed
        $investment->load([
            'investmentGroup',
            'currency',
        ]);

        // Get all transactions related to selected investment
        $rawTransactions =
            Transaction::with([
                'config',
                'transactionType',
            ])
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($investment) {
                    $query->Where('investment_id', $investment->id);
                }
            )
            ->orderBy('date')
            ->get();

        // Process data for table and chart
        $rawTransactions
            ->transform(function ($transaction) {
                $commonData =
                    [
                        'id' => $transaction->id,
                        'transaction_type' => $transaction->transactionType->toArray(),
                        'amount_operator' => $transaction->transactionType->amount_operator,
                        'quantity_operator' => $transaction->transactionType->quantity_operator,

                        'reconciled' => $transaction->reconciled,
                        'comment' => $transaction->comment,
                    ];

                $baseData = [
                    'quantity' => $transaction->config->quantity,
                    'price' => $transaction->config->price,
                    'dividend' => $transaction->config->dividend,
                    'commission' => $transaction->config->commission,
                    'tax' => $transaction->config->tax,
                ];

                if ($transaction->schedule) {
                    $transaction->load(['transactionSchedule']);

                    $dateData = [
                        'schedule' => $transaction->transactionSchedule,
                        'transaction_group' => 'schedule',
                    ];
                } else {
                    $dateData = [
                        'date' => $transaction->date,
                        'transaction_group' => 'history',
                    ];
                }

                return array_merge($commonData, $baseData, $dateData);
            });

        // Get all historical transactions
        $transactions = $rawTransactions->where('transaction_group', 'history');

        // Add all scheduled items to list of transactions
        $rawTransactions
            ->where('transaction_group', 'schedule')
            ->each(function ($transaction) use (&$transactions) {
                $rule = new Rule();
                $rule->setStartDate(new Carbon($transaction['schedule']->start_date));

                if ($transaction['schedule']->end_date) {
                    $rule->setUntil(new Carbon($transaction['schedule']->end_date));
                }

                $rule->setFreq($transaction['schedule']->frequency);

                if ($transaction['schedule']->count) {
                    $rule->setCount($transaction['schedule']->count);
                }
                if ($transaction['schedule']->interval) {
                    $rule->setInterval($transaction['schedule']->interval);
                }

                $transformerConfig = new ArrayTransformerConfig();
                $transformerConfig->enableLastDayOfMonthFix();
                // Avoid overloading too frequent schedules. TODO: notify user if limit is reached.
                $transformerConfig->setVirtualLimit(500);

                $transformer = new ArrayTransformer();
                $transformer->setConfig($transformerConfig);

                $startDate = new Carbon($transaction['schedule']->next_date);
                $startDate->startOfDay();

                if ($transaction['schedule']->end_date === null) {
                    $endDate = Auth::user()->end_date;
                } else {
                    $endDate = new Carbon($transaction['schedule']->end_date);
                }
                $endDate->startOfDay();

                $constraint = new BetweenConstraint($startDate, $endDate, true);

                $first = true;

                foreach ($transformer->transform($rule, $constraint) as $instance) {
                    $newTransaction = $transaction;
                    $newTransaction['date'] = new Carbon($instance->getStart());
                    $newTransaction['transaction_group'] = 'forecast';
                    $newTransaction['schedule_is_first'] = $first;

                    $transactions->push($newTransaction);

                    $first = false;
                }
            });

        // Calculate historical and scheduled quantity changes for chart
        $runningTotal = 0;
        $runningSchedule = 0;
        $quantities = $transactions
            // TODO: group by date
            ->sortBy('date')
            ->map(function ($transaction) use (&$runningTotal, &$runningSchedule) {
                $operator = $transaction['quantity_operator'];
                if (! $operator) {
                    $quantity = 0;
                } else {
                    $quantity = ($operator === 'minus' ? -1 : 1) * $transaction['quantity'];
                }

                $runningSchedule += $quantity;
                if ($transaction['transaction_group'] === 'history') {
                    $runningTotal += $quantity;
                }

                return [
                    'date' => $transaction['date']->format('Y-m-d'),
                    'quantity' => $runningTotal,
                    'schedule' => $runningSchedule,
                ];
            });

        JavaScriptFacade::put([
            'investment' => $investment,
            'transactions' => array_values($transactions->toArray()),
            'prices' => $prices,
            'quantities' => array_values($quantities->toArray()),
        ]);

        return view('investment.show', [
            'investment' => $investment,
        ]);
    }

    /**
     * Display view with timeline chart.
     *
     * @return View
     */
    public function timeline(): View
    {
        /**
         * @get('/investment/timeline')
         * @name('investment.timeline')
         * @middlewares('web', 'auth', 'verified')
         */
        return view('investment.timeline');
    }
}
