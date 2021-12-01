<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use JavaScript;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;

class InvestmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Investment::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all investments of the user from the database and return to view
        $investments = Auth::user()
            ->investments()
            ->with([
                'currency',
                'investment_group',
                'investment_price_provider'
            ])
            ->get();

        // Pass data for DataTables
        JavaScript::put([
            'investments' => $investments,
        ]);

        return view('investment.index');
    }

    public function edit(Investment $investment)
    {
        return view('investment.form', [
            'investment'=> $investment,
        ]);
    }

    public function update(InvestmentRequest $request, Investment $investment)
    {
        // Retrieve the validated input data
        $validated = $request->validated();
        $investment->fill($validated);
        $investment->save();

        self::addSimpleSuccessMessage('Investment updated');

        return redirect()->route('investment.index');
    }

    public function create()
    {
        return view('investment.form');
    }

    public function store(InvestmentRequest $request)
    {
        $validated = $request->validated();

        $investment = Investment::make($validated);
        $investment->user_id = Auth::user()->id;
        $investment->save();

        self::addSimpleSuccessMessage('Investment added');

        return redirect()->route('investment.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Investment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Investment $investment)
    {
        $investment->delete();

        self::addSimpleSuccessMessage('Investment deleted');

        return redirect()->route('investment.index');
    }

    public function summary()
    {
        // Show all investments from the database and return to view
        $investments = Auth::user()
            ->investments()
            ->with([
                'currency',
                'investment_group',
            ])
            ->get();

        $investments->map(function ($investment) {
            $investment['price'] = $investment->getLatestPrice();
            $investment['quantity'] = $investment->getCurrentQuantity();

            return $investment;
        });

        // Pass data for DataTables
        JavaScript::put([
            'investments' => $investments,
        ]);

        return view('investment.summary');
    }

    public function show(Investment $investment)
    {
        // Get all stored price points
        $prices = InvestmentPrice::where('investment_id', $investment->id)
            ->orderBy('date')
            ->get();

        // Eager load investment details to be displayed
        $investment->load([
            'investment_group',
            'currency',
            'investment_price_provider',
        ]);

        // Get all transactions related to selected investment
        $rawTransactions =
            Transaction::with([
                'config',
                'config.investment',
                'transactionType',
            ])
            ->whereHasMorph(
                'config',
                [\App\Models\TransactionDetailInvestment::class],
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
                        'transaction_name' => $transaction->transactionType->name,
                        'transaction_type' => $transaction->transactionType->type,
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

                $transformer = new ArrayTransformer();

                $transformerConfig = new ArrayTransformerConfig();
                $transformerConfig->setVirtualLimit(100);
                $transformerConfig->enableLastDayOfMonthFix();
                $transformer->setConfig($transformerConfig);

                $startDate = new Carbon($transaction['schedule']->next_date);
                $startDate->startOfDay();
                if (is_null($transaction['schedule']->end_date)) {
                    $endDate = (new Carbon())->addYears(25); //TODO: get end date from settings, and/or display default setting
                } else {
                    $endDate = new Carbon($transaction['schedule']->end_date);
                }
                $endDate->startOfDay();

                $constraint = new BetweenConstraint($startDate, $endDate, true);

                $first = true;

                foreach ($transformer->transform($rule, $constraint) as $instance) {
                    $newTransaction = $transaction;
                    $newTransaction['date'] = $instance->getStart()->format('Y-m-d');
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
            ->map(function ($transaction) use (&$runningTotal, &$runningSchedule) {
                $operator = $transaction['quantity_operator'];
                if (! $operator) {
                    $quantity = 0;
                } else {
                    $quantity = ($operator == 'minus' ? -1 : 1) * $transaction['quantity'];
                }

                $runningSchedule += $quantity;
                if ($transaction['transaction_group'] == 'history') {
                    $runningTotal += $quantity;
                }

                return [
                        'date' => $transaction['date'],
                        'quantity' => $runningTotal,
                        'schedule' => $runningSchedule,
                    ];
            });

        JavaScript::put([
            'investment' => $investment,
            'transactions' => array_values($transactions->toArray()),
            'prices' => $prices,
            'quantities' => array_values($quantities->toArray()),
        ]);

        return view('investment.show', [
            'investment' => $investment,
        ]);
    }
}
