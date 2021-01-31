<?php

namespace App\Http\Controllers;

use App\Currency;
use App\CurrencyRate;
use App\Http\Requests\CurrencyRequest;
use Illuminate\Http\Request;
use JavaScript;

class CurrencyController extends Controller
{
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
            'editUrl' => route('currencies.edit', '#ID#'),
            'deleteUrl' => action('CurrencyController@destroy', '#ID#'),
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

        add_notification('Currency added', 'success');

        return redirect()->route('currencies.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $currency = Currency::find($id);

        return view('currencies.form',['currency'=> $currency]);
    }

    public function update(CurrencyRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $currency = Currency::find($request->input('id'));
        $currency->fill($validated);
        $currency->save();

        add_notification('Currency updated', 'success');

        return redirect()->route('currencies.index');
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
        $currency = Currency::find($id);

        //base currency cannot be deleted
        if ($currency->base) {
            add_notification('Base currency cannot be deleted', 'danger');
            return redirect()->back();
        }

        //delete
        try {
            $currency->delete();
            add_notification('Currency deleted', 'success');
            return redirect()->route('currencies.index');
        } catch(\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                add_notification('Currency is in use, cannot be deleted', 'danger');
            } else {
                add_notification('Database error: ' . $e->errorInfo[2], 'danger');
            }
            return redirect()->back();
        }
    }

}
