<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class CurrencyRateController extends Controller
{
    use CurrencyTrait;

    protected $currencyRate;

    public function __construct(CurrencyRate $currencyRate)
    {
        $this->middleware(['auth', 'verified']);
        $this->currencyRate = $currencyRate;
    }

    public function index(Currency $from, Currency $to)
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

        return view(
            'currencyrates.index',
            with([
                'from' => $from,
                'to' => $to,
            ])
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  CurrencyRate  $currencyRate
     * @return \Illuminate\Http\Response
     */
    public function destroy(CurrencyRate $currencyRate)
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

    public function retreiveCurrencyRateToBase(Currency $currency, ?Carbon $from = null)
    {
        /**
         * @get('/currencyrates/get/{currency}/{from?}')
         * @name('currencyrate.retreiveRate')
         * @middlewares('web')
         */

        // Authorize user access to requested currency
        $this->authorize('view', $currency);

        $currency->retreiveCurrencyRateToBase($from);

        return redirect()->back();
    }

    public function retreiveMissingCurrencyRateToBase(Currency $currency)
    {
        /**
         * @get('/currencyrates/missing/{currency}')
         * @name('currencyrate.retreiveMissing')
         * @middlewares('web')
         */
        // Authorize user access to requested currency
        $this->authorize('view', $currency);

        $currency->retreiveMissingCurrencyRateToBase();

        return redirect()->back();
    }
}
