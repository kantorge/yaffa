<?php

namespace App\Http\Controllers;

use AmrShawky\LaravelCurrency\Facade\Currency as CurrencyApi;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;
use JavaScript;

class CurrencyRateController extends Controller
{
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
                'to'=> $to,
            ])
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  CurrencyRate  $tag
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
        $baseCurrency = Currency::where('base', 1)->firstOr(function () {
            return Currency::orderBy('id')->firstOr(function () {
                return null;
            });
        });

        if ($baseCurrency->id === $currency->id) {
            return 1;
        }

        $date = Carbon::create('yesterday');
        if (! $from) {
            $from = Carbon::create('yesterday');
        }

        $rates = CurrencyApi::rates()
            ->timeSeries($from->format('Y-m-d'), $date->format('Y-m-d'))
            ->base($currency->iso_code)
            ->symbols([$baseCurrency->iso_code])
            ->get();

        foreach ($rates as $date => $rate) {
            CurrencyRate::updateOrCreate(
                [
                    'from_id' => $currency->id,
                    'to_id' => $baseCurrency->id,
                    'date' => $date,
                ],
                [
                    'rate' => $rate[$baseCurrency->iso_code],
                ]
            );
        }

        return redirect()->back();
    }

    public function retreiveMissingCurrencyRateToBase(Currency $currency)
    {
        $baseCurrency = Currency::where('base', 1)->firstOr(function () {
            return Currency::orderBy('id')->firstOr(function () {
                return null;
            });
        });

        $rate = CurrencyRate::where('from_id', $currency->id)
                                    ->where('to_id', $baseCurrency->id)
                                    ->latest('date')
                                    ->first();

        // Fallback to last 30 days
        if (! $rate) {
            $rate = new CurrencyRate([
                'from_id' => $currency->id,
                'to_id' => $baseCurrency->id,
                'date' => Carbon::create('30 days ago'),
            ]);
        }

        return $this->retreiveCurrencyRateToBase($currency, $rate->date);
    }
}
