<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class CurrencyRateController extends Controller
{
    use CurrencyTrait;

    protected CurrencyRate $currencyRate;

    public function __construct(CurrencyRate $currencyRate)
    {
        $this->middleware(['auth', 'verified']);
        $this->currencyRate = $currencyRate;
    }

    /**
     * Display a listing of the resource, based on the selected currencies.
     *
     * @param Currency $from
     * @param Currency $to
     * @return View
     * @throws AuthorizationException
     */
    public function index(Currency $from, Currency $to): View
    {
        /**
         * @get('/currencyrates/{from}/{to}')
         * @name('currency-rate.index')
         * @middlewares('web')
         */

        // Authorize user access to requested currencies
        $this->authorize('view', $from);
        $this->authorize('view', $to);

        $currencyRates = $this->currencyRate
            ->where('from_id', $from->id)
            ->where('to_id', $to->id)
            ->orderBy('date')
            ->get();

        JavaScriptFacade::put(['currencyRates' => $currencyRates]);

        return view('currency-rate.index', [
            'from' => $from,
            'to' => $to,
            'currencyRates' => $currencyRates,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param CurrencyRate $currencyRate
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function destroy(CurrencyRate $currencyRate): RedirectResponse
    {
        /**
         * @delete('/currency-rate/{currency_rate}')
         * @name('currency-rate.destroy')
         * @middlewares('web')
         */

        // Authorize user access to requested currencies
        $this->authorize('view', $currencyRate->currencyFrom);
        $this->authorize('view', $currencyRate->currencyTo);

        $currencyRate->delete();

        self::addSimpleSuccessMessage(__('Currency rate deleted'));

        return redirect()->back();
    }

    public function retrieveCurrencyRateToBase(Currency $currency, ?Carbon $from = null): RedirectResponse
    {
        /**
         * @get('/currencyrates/get/{currency}/{from?}')
         * @name('currency-rate.retrieveRate')
         * @middlewares('web')
         */

        // Authorize user access to requested currency
        $this->authorize('view', $currency);

        $currency->retrieveCurrencyRateToBase($from);

        return redirect()->back();
    }

    public function retrieveMissingCurrencyRateToBase(Currency $currency): RedirectResponse
    {
        /**
         * @get('/currencyrates/missing/{currency}')
         * @name('currency-rate.retrieveMissing')
         * @middlewares('web')
         */
        // Authorize user access to requested currency
        $this->authorize('view', $currency);

        $currency->retrieveMissingCurrencyRateToBase();

        return redirect()->back();
    }
}
