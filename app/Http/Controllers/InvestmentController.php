<?php

namespace App\Http\Controllers;

use App\Investment;
use App\InvestmentGroup;
use App\InvestmentPriceProvider;
use App\Currency;
use App\Transaction;
use App\Http\Requests\InvestmentRequest;
use App\InvestmentPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JavaScript;

class InvestmentController extends Controller
{
    protected $investment;

    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all investments from the database and return to view
        $investments = $this
            ->investment
            ->get();

        //pass data for DataTables
        JavaScript::put([
            'investments' => $investments,
            'editUrl' => route('investments.edit', '#ID#'),
            'deleteUrl' => action('InvestmentController@destroy', '#ID#'),
        ]);

        return view('investments.index');
    }

    public function edit($id)
    {
        $investment = $this->investment->find($id);

        //get all investment groups
        $allInvestmentGropus = InvestmentGroup::pluck('name', 'id')->all();

        //get all currencies
        $allCurrencies = Currency::pluck('name', 'id')->all();

        //get all price providers
        $allInvestmentPriceProviders = InvestmentPriceProvider::pluck('name', 'id')->all();

        return view('investments.form', [
            'investment'=> $investment,
            'allInvestmentGropus' => $allInvestmentGropus,
            'allCurrencies' => $allCurrencies,
            'allInvestmentPriceProviders' => $allInvestmentPriceProviders,
        ]);
    }

    public function update(InvestmentRequest $request, Investment $investment)
    {
        // Retrieve the validated input data
        $validated = $request->validated();
        $investment->fill($validated);
        $investment->save();

        add_notification('Investment updated', 'success');

        return redirect()->route('investments.index');
    }

    public function create()
    {
        //get all investment groups
        $allInvestmentGropus = InvestmentGroup::pluck('name', 'id')->all();

        //get all currencies
        $allCurrencies = Currency::pluck('name', 'id')->all();

        //get all price providers
        $allInvestmentPriceProviders = InvestmentPriceProvider::pluck('name', 'id')->all();

        return view('investments.form', [
            'allInvestmentGropus' => $allInvestmentGropus,
            'allCurrencies' => $allCurrencies,
            'allInvestmentPriceProviders' => $allInvestmentPriceProviders,
        ]);
    }

    public function store(InvestmentRequest $request)
    {

        $validated = $request->validated();
        $investment = Investment::create($validated);
        $investment->save();

        add_notification('Investment added', 'success');

        return redirect()->route('investments.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Retrieve item
        $investment = Investment::find($id);
        //delete
        $investment->delete();

        add_notification('Investment deleted', 'success');

        return redirect()->route('investments.index');
    }

    public function summary()
    {
        //Show all investments from the database and return to view
        $investments = $this
            ->investment
            ->with([
                'currency',
                'investment_group',
            ])
            ->get();

        /*
        //Show all currencies from the database and return to view
        $currencies = Currency::all();

        //support DataTables with action URLs
        $currencies->map(function ($currency) {
            $currency['latest_rate'] = $currency->rate();
            return $currency;
        });
        */

        $investments->map(function($investment) {
            $investment['price'] = $investment->getLatestPrice();
            $investment['quantity'] = $investment->getCurrentQuantity();

            return $investment;
        });

        //pass data for DataTables
        JavaScript::put([
            'investments' => $investments,
            'urlDetails' => route('investments.show', '#ID#'),
        ]);

        return view('investments.summary');
    }

    public function show(Investment $investment)
    {
        //eager load investment details to be displayed
        $investment->load([
            'investment_group',
            'currency',
            'investment_price_provider',
        ]);

        //get all investment transactions related to selected investment
        $investmentTransactions = Transaction::
            where(function($query) {
                $query->where('schedule', 1)
                    ->orWhere(function($query) {
                    $query->where('schedule', 0);
                    $query->where('budget', 0);
                });
            })
            ->whereHasMorph(
                'config',
                [\App\TransactionDetailInvestment::class],
                function (Builder $query) use ($investment) {
                    $query->Where('investment_id', $investment->id);
                }
            )
            ->with(
                [
                    'config',
                    'config.investment',
                    'transactionType',
                ])
            ->get();

        //process historical data for table and chart
        $transactions = $investmentTransactions
            ->map(function ($transaction) {
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

                if($transaction->schedule) {
                    $transaction->load(['transactionSchedule']);

                    $dateData = [
                        'schedule' => $transaction->transactionSchedule,
                        'transaction_group' => 'schedule',
                        'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->format("Y-m-d") : null),
                    ];
                } else {
                    $dateData = [
                        'date' => $transaction->date,
                        'transaction_group' => 'history',
                    ];
                }

                return array_merge($commonData, $baseData, $dateData);

            });

        //get all stored price points
        $prices = InvestmentPrice::
            where('investment_id', $investment->id)
            ->orderBy('date')
            ->get();

        //calculate historical quantity changes
        $runningTotal = 0;
        $quantities = $investmentTransactions
            ->map(function($transaction) use (&$runningTotal) {
                $operator = $transaction->transactionType->quantity_operator;
                    if (!$operator) {
                        $quantity = 0;
                    } else {
                        $quantity = ($operator == 'minus'
                                   ? - $transaction->config->quantity
                                   : $transaction->config->quantity);
                    }

                    $runningTotal += $quantity;

                    return [
                        'date' => $transaction->date,
                        'quantity' => $runningTotal,
                    ];
            });

        JavaScript::put([
            'investment' => $investment,
            'transactions' => $transactions,
            'prices' => $prices,
            'quantities' => $quantities,
            'urlEditInvestment' => route('transactions.editInvestment', '#ID#'),
            'urlCloneInvestment' => route('transactions.cloneInvestment', '#ID#'),
            'urlDelete' => action('TransactionController@destroy', '#ID#'),
            //'urlSkip' => route('transactions.skipScheduleInstance', '#ID#'),
            //'urlEnterWithEditStandard' => route('transactions.enterWithEditStandard', '#ID#'),
        ]);

        return view('investments.show', [
            'investment' => $investment,
        ]);
    }
}
