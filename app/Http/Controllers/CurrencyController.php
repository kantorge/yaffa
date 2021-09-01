<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use JavaScript;

class CurrencyController extends Controller
{
    use CurrencyTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all currencies from the database and return to view
        $currencies = Currency::all();

        $baseCurrency = Currency::where('base', 1)->firstOr(function () {
            return Currency::orderBy('id')->firstOr(function () {
                return null;
            });
        });

        //support DataTables with action URLs
        $currencies->map(function ($currency) {
            $currency['latest_rate'] = $currency->rate();
            return $currency;
        });

        //pass data for DataTables
        JavaScript::put([
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency,
        ]);

        return view('currencies.index');
    }

    public function create()
    {
        return view('currencies.form');
    }

    public function store(CurrencyRequest $request)
    {

        $validated = $request->validated();

        $currency = New Currency();
        $currency->fill($validated);
        $currency->save();

        self::addSimpleSuccessMessage('Currency added');

        return redirect()->route('currencies.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Currency $currency)
    {
        return view('currencies.form', ['currency'=> $currency]);
    }

    public function update(CurrencyRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $currency = Currency::find($request->input('id'));
        $currency->fill($validated);
        $currency->save();

        self::addSimpleSuccessMessage('Currency updated');

        return redirect()->route('currencies.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Currency $currency)
    {
        //base currency cannot be deleted
        if ($currency->base) {
            self::addSimpleDangerMessage('Base currency cannot be deleted');
            return redirect()->back();
        }

        //delete
        try {
            $currency->delete();
            self::addSimpleSuccessMessage('Currency deleted');
            return redirect()->route('currencies.index');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Currency is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: ' . $e->errorInfo[2]);
            }
            return redirect()->back();
        }
    }

    public function setDefault(Currency $currency)
    {
        $baseCurrency = $this->getBaseCurrency();

        if ($currency == $baseCurrency) {
            return redirect()->back();
        }

        $baseCurrency->base = null;
        $baseCurrency->save();
        $currency->base = true;
        $currency->save();

        self::addSimpleSuccessMessage('Base currency changed');
        return redirect()->back();
    }
}
