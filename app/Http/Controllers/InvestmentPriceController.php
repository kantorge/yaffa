<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Models\Investment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentPriceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
        ];
    }

    /**
     * Display the investment price list using Vue component manager.
     *
     * @throws AuthorizationException
     */
    public function list(Investment $investment): View
    {
        /**
         * @get('/investment-price/list/{investment}')
         * @name('investment-price.list')
         * @middlewares('web', 'auth', 'verified')
         */
        Gate::authorize('view', $investment);

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
