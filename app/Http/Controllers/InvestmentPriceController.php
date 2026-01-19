<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\InvestmentPriceRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentPriceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    /**
     * @throws AuthorizationException
     */
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

    public function retrieveInvestmentPrice(Investment $investment): RedirectResponse
    {
        /**
         * @get('/investment-price/get/{investment}/{from?}')
         * @name('investment-price.retrieve')
         * @middlewares('web', 'auth', 'verified')
         */

        // Get latest known date of price date, so we can retrieve missing values
        $lastPrice = $investment->investmentPrices->last('date');
        $date = $lastPrice ? $lastPrice->date : Carbon::now()->subDays(30);

        $investment->getInvestmentPriceFromProvider($date);

        // Use the InvestmentService to recalculate the related accounts
        $investmentService = new InvestmentService();
        $investmentService->recalculateRelatedAccounts($investment);

        self::addSimpleSuccessMessage(__('Investment prices successfully downloaded from :date', ['date' => $date->toFormattedDateString()]));

        return redirect()->back();
    }
}
