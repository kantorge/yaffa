<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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
     * @param Investment $investment
     * @return View
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

        // Pass data for DataTables
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

    public function create(Request $request): View
    {
        /**
         * @get('/investment-price/create')
         * @name('investment-price.create')
         * @middlewares('web', 'auth', 'verified')
         */
        $investment = Investment::find($request->get('investment'));
        Gate::authorize('view', $investment);

        return view(
            'investment-prices.form',
            [
                'investment' => $investment,
            ]
        );
    }

    public function store(InvestmentPriceRequest $request): RedirectResponse
    {
        /**
         * @post('/investment-price')
         * @name('investment-price.store')
         * @middlewares('web', 'auth', 'verified')
         */
        $investment = Investment::find($request->investment_id);
        Gate::authorize('view', $investment);

        $validated = $request->validated();

        InvestmentPrice::create($validated);

        self::addSimpleSuccessMessage(__('Investment price added'));

        return redirect()->route('investment-price.list', $investment);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  InvestmentPrice  $investmentPrice
     * @return View
     */
    public function edit(InvestmentPrice $investmentPrice): View
    {
        /**
         * @get('/investment-price/{investment_price}/edit')
         * @name('investment-price.edit')
         * @middlewares('web', 'auth', 'verified')
         */
        return view(
            'investment-prices.form',
            [
                'investment' => $investmentPrice->investment,
                'investmentPrice' => $investmentPrice,
            ]
        );
    }

    public function update(InvestmentPriceRequest $request): RedirectResponse
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/investment-price/{investment_price}')
         * @name('investment-price.update')
         * @middlewares('web', 'auth', 'verified')
         */
        $validated = $request->validated();

        InvestmentPrice::find($request->input('id'))
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Investment price updated'));

        return redirect()->route('investment-price.list', $request->investment_id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  InvestmentPrice  $investmentPrice
     * @return RedirectResponse
     */
    public function destroy(InvestmentPrice $investmentPrice): RedirectResponse
    {
        /**
         * @delete('/investment-price/{investment_price}')
         * @name('investment-price.destroy')
         * @middlewares('web', 'auth', 'verified')
         */
        $investmentPrice->delete();

        self::addSimpleSuccessMessage(__('Investment price deleted'));

        return redirect()->back();
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
