<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\CurrencyRate;
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
                            ->get();

        JavaScript::put(['currencyRates' => $currencyRates]);

        return view('currencyrates.index');
    }
}
