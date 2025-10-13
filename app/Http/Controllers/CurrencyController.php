<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Http\Requests\CurrencyRequest;
use App\Http\Traits\CurrencyTrait;
use App\Jobs\GetCurrencyRates as GetCurrencyRatesJob;
use App\Models\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class CurrencyController extends Controller
{
    use CurrencyTrait;

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('can:viewAny,App\Models\Currency')->only('index');
        $this->middleware('can:view,currency')->only('show');
        $this->middleware('can:create,App\Models\Currency')->only('create', 'store');
        $this->middleware('can:update,currency')->only('edit', 'update');
        $this->middleware('can:delete,currency')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        /**
         * @get('/currency')
         * @name('currency.index')
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

        return view('currency.index');
    }

    public function create(): View
    {
        /**
         * @get('/currency/create')
         * @name('currency.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Currency')
         */
        return view('currency.form');
    }

    public function store(CurrencyRequest $request): RedirectResponse
    {
        /**
         * @post('/currency')
         * @name('currency.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Currency')
         */
        $currency = $request->user()->currencies()->create($request->validated());

        // The first currency created will be automatically set as the base currency
        if ($request->user()->currencies->count() === 1) {
            $currency->base = true;
            $currency->save();
        }

        self::addSimpleSuccessMessage(__('Currency added'));

        return redirect()->route('currency.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Currency $currency
     * @return View
     */
    public function edit(Currency $currency): View
    {
        /**
         * @get('/currency/{currency}/edit')
         * @name('currency.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,currency')
         */

        // Get all currencies, as base currency setting is defined based on this
        $currencies = Auth::user()
            ->currencies()
            ->get();

        return view('currency.form')
            ->with('currency', $currency)
            ->with('currencies', $currencies);
    }

    public function update(CurrencyRequest $request, Currency $currency): RedirectResponse
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/currency/{currency}')
         * @name('currency.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,currency')
         */
        $validated = $request->validated();

        $currency->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Currency updated'));

        return redirect()->route('currency.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Currency $currency
     * @return Response|RedirectResponse
     */
    public function destroy(Currency $currency): Response|RedirectResponse
    {
        /**
         * @delete('/currency/{currency}')
         * @name('currency.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,currency')
         */
        // Base currency cannot be deleted
        if ($currency->base) {
            self::addSimpleErrorMessage(__('Base currency cannot be deleted'));

            return redirect()->back();
        }

        try {
            $currency->delete();
            self::addSimpleSuccessMessage(__('Currency deleted'));

            return redirect()->route('currency.index');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                self::addSimpleErrorMessage(__('Currency is in use, cannot be deleted'));
            } else {
                self::addSimpleErrorMessage(__('Database error:') . ' ' . $e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function setDefault(Currency $currency): RedirectResponse
    {
        /**
         * @get('/currency/{currency}/setDefault')
         * @name('currency.setDefault')
         * @middlewares('web', 'auth', 'verified')
         */
        // Authenticate the user against the currency using CurrencyPolicy
        Gate::authorize('update', $currency);

        if ($currency->setToBase()) {
            self::addSimpleSuccessMessage(__('Base currency changed'));

            // Invalidate the cache for the base currency for the current user
            $cacheKey = "baseCurrency_forUser_{$currency->user_id}";
            Cache::forget($cacheKey);

            // Get all non-base, updatable currencies of the user, and dispatch the currency rate retrieval job
            $currencies = $currency->user
                ->currencies()
                ->notBase()
                ->autoUpdate()
                ->get();
            $currencies->each(function ($currency) {
                GetCurrencyRatesJob::dispatch($currency);
            });
        } else {
            self::addSimpleErrorMessage(__('Failed to change base currency'));
        }

        return redirect()->back();
    }
}
