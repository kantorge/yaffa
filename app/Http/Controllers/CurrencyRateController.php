<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class CurrencyRateController extends Controller implements HasMiddleware
{
    use CurrencyTrait;

    protected CurrencyRate $currencyRate;

    public function __construct(CurrencyRate $currencyRate)
    {

        $this->currencyRate = $currencyRate;
    }

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    /**
     * Display a listing of the resource, based on the selected currencies.
     *
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
        Gate::authorize('view', $from);
        Gate::authorize('view', $to);

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
}
