<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

#[Middleware('auth')]
#[Middleware('verified')]
class InvestmentPriceController extends Controller
{
    /**
     * Display the investment price list using Vue component manager.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'investment')]
    public function list(Investment $investment): View
    {
        /**
         * @get('/investment-price/list/{investment}')
         * @name('investment-price.list')
         * @middlewares('web', 'auth', 'verified')
         */
        // Load currency details for JavaScript
        $investment->load('currency');

        $pricesOrdered = DB::table('investment_prices')
            ->select('id', 'date', 'price')
            ->where('investment_id', $investment->id)
            ->orderBy('date')
            ->get();

        // Pass data for Vue components
        JavaScriptFacade::put([
            'investment' => $investment,
            'prices' => $pricesOrdered,
        ]);

        return view(
            'investment-prices.list',
            [
                'investment' => $investment,
                'prices' => $pricesOrdered,
            ]
        );
    }
}
