<?php

namespace App\Http\Controllers;

use App\Investment;
use App\InvestmentGroup;
use App\InvestmentPriceProvider;
use App\Currency;
use App\Http\Requests\InvestmentRequest;
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

    public function show(Investment $investment)
    {
        return view('investments.show', compact('investment'));
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

    public function summary($withClosed = null)
    {
        //Show all investments from the database and return to view
        $investments = $this
            ->investment
            ->get();

        //pass data for DataTables
        JavaScript::put([
            'investments' => $investments,
        ]);

        return view('investments.summary');
    }
}
