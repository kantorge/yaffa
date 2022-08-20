<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;
use JavaScript;

class CurrencyRateController extends Controller
{
    use CurrencyTrait;

    protected $currencyRate;

    public function __construct(CurrencyRate $currencyRate)
    {
        $this->currencyRate = $currencyRate;
    }

    public function index(Currency $from, Currency $to)
    {
        $currencyRates = $this->currencyRate
                            ->where('from_id', $from->id)
                            ->where('to_id', $to->id)
                            ->orderBy('date')
                            ->get();

        JavaScript::put(['currencyRates' => $currencyRates]);

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
        $currencyRate->delete();

        self::addSimpleSuccessMessage('Currency rate deleted');

        return redirect()->back();
    }

    public function retreiveCurrencyRateToBase(Currency $currency, ?Carbon $from = null)
    {
        $currency->retreiveCurrencyRateToBase($from);

        return redirect()->back();
    }

    public function retreiveMissingCurrencyRateToBase(Currency $currency)
    {
        $currency->retreiveMissingCurrencyRateToBase();

        return redirect()->back();
    }
}
