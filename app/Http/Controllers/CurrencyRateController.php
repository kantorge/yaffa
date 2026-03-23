<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;

class CurrencyRateController extends Controller implements HasMiddleware
{
    use CurrencyTrait;

    public static function middleware(): array
    {
        return ['auth', 'verified'];
    }

    /**
     * Display a listing of the resource, based on the selected currencies.
     *
     * @throws AuthorizationException
     */
    public function index(Currency $from, Currency $to): View
    {
        /**
         * @get("/currencyrates/{from}/{to}")
         * @name("currency-rate.index")
         * @middlewares("web")
         */

        // Authorize user access to requested currencies
        Gate::authorize('view', $from);
        Gate::authorize('view', $to);

        return view('currency-rates.index', [
            'from' => $from,
            'to' => $to,
        ]);
    }
}
