<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class CurrencyController extends Controller
{
    use CurrencyTrait;

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(Currency::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        /**
         * @get('/currencies')
         * @name('currencies.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\Currency')
         */
        // Show all currencies of user from the database and return to view
        $currencies = Auth::user()
            ->currencies()
            ->get();

        $currencies->map(function ($currency) {
            $currency['latest_rate'] = $currency->rate();

            return $currency;
        });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'currencies' => $currencies,
        ]);

        return view('currencies.index');
    }

    public function create(): View
    {
        /**
         * @get('/currencies/create')
         * @name('currencies.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Currency')
         */
        return view('currencies.form');
    }

    public function store(CurrencyRequest $request)
    {
        /**
         * @post('/currencies')
         * @name('currencies.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Currency')
         */
        Currency::create($request->validated());

        self::addSimpleSuccessMessage(__('Currency added'));

        return redirect()->route('currencies.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Currency $currency
     * @return View
     */
    public function edit(Currency $currency)
    {
        /**
         * @get('/currencies/{currency}/edit')
         * @name('currencies.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,currency')
         */
        return view('currencies.form', ['currency' => $currency]);
    }

    public function update(CurrencyRequest $request, Currency $currency)
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/currencies/{currency}')
         * @name('currencies.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,currency')
         */
        $validated = $request->validated();

        $currency->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Currency updated'));

        return redirect()->route('currencies.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Currency $currency)
    {
        /**
         * @delete('/currencies/{currency}')
         * @name('currencies.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,currency')
         */
        // Base currency cannot be deleted
        if ($currency->base) {
            self::addSimpleDangerMessage(__('Base currency cannot be deleted'));

            return redirect()->back();
        }

        //delete
        try {
            $currency->delete();
            self::addSimpleSuccessMessage(__('Currency deleted'));

            return redirect()->route('currencies.index');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                self::addSimpleDangerMessage(__('Currency is in use, cannot be deleted'));
            } else {
                self::addSimpleDangerMessage(__('Database error:') . ' ' . $e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }

    public function setDefault(Currency $currency)
    {
        /**
         * @get('/currencies/{currency}/setDefault')
         * @name('currencies.setDefault')
         * @middlewares('web', 'auth', 'verified')
         */
        $baseCurrency = $this->getBaseCurrency();

        if ($currency->id === $baseCurrency->id) {
            return redirect()->back();
        }

        $baseCurrency->base = null;
        $baseCurrency->save();
        $currency->base = true;
        $currency->save();

        self::addSimpleSuccessMessage(__('Base currency changed'));

        return redirect()->back();
    }
}
