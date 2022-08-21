<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class CurrencyController extends Controller
{
    use CurrencyTrait;

    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Currency::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        /**
         * @get('/currencies')
         * @name('currencies.index')
         * @middlewares('web', 'auth', 'can:viewAny,App\Models\Currency')
         */
        // Show all currencies of user from the database and return to view
        $currencies = Auth::user()
            ->currencies()
            ->get();

        $baseCurrency = $this->getBaseCurrency();

        $currencies->map(function ($currency) {
            $currency['latest_rate'] = $currency->rate();

            return $currency;
        });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency,
        ]);

        return view('currencies.index');
    }

    public function create()
    {
        /**
         * @get('/currencies/create')
         * @name('currencies.create')
         * @middlewares('web', 'auth', 'can:create,App\Models\Currency')
         */
        return view('currencies.form');
    }

    public function store(CurrencyRequest $request)
    {
        /**
         * @post('/currencies')
         * @name('currencies.store')
         * @middlewares('web', 'auth', 'can:create,App\Models\Currency')
         */
        $validated = $request->validated();

        $currency = Currency::make($validated);
        $currency->user_id = Auth::user()->id;
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
        /**
         * @get('/currencies/{currency}/edit')
         * @name('currencies.edit')
         * @middlewares('web', 'auth', 'can:update,currency')
         */
        return view('currencies.form', ['currency' => $currency]);
    }

    public function update(CurrencyRequest $request, Currency $currency)
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/currencies/{currency}')
         * @name('currencies.update')
         * @middlewares('web', 'auth', 'can:update,currency')
         */
        $validated = $request->validated();

        $currency->fill($validated)
            ->save();

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
        /**
         * @delete('/currencies/{currency}')
         * @name('currencies.destroy')
         * @middlewares('web', 'auth', 'can:delete,currency')
         */
        // Base currency cannot be deleted
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
                self::addSimpleDangerMessage('Database error: '.$e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }

    public function setDefault(Currency $currency)
    {
        /**
         * @get('/currencies/{currency}/setDefault')
         * @name('currencies.setDefault')
         * @middlewares('web', 'auth')
         */
        $baseCurrency = $this->getBaseCurrency();

        if ($currency->id === $baseCurrency->id) {
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
