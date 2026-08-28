<?php

namespace App\Http\Controllers;

use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

#[Middleware('auth')]
#[Middleware('verified')]
class CurrencyRateController extends Controller
{
    use CurrencyTrait;

    /**
     * Display a listing of the resource, based on the selected currencies.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'from')]
    public function index(Currency $from, Currency $to): View
    {
        /**
         * @get("/currencyrates/{from}/{to}")
         * @name("currency-rate.index")
         * @middlewares("web")
         */

        // Authorize user access to requested currencies
        Gate::authorize('view', $to);

        return view('currency-rates.index', [
            'from' => $from,
            'to' => $to,
        ]);
    }
}
