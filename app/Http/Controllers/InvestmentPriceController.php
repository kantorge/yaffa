<?php

namespace App\Http\Controllers;

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

class InvestmentPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
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
        $this->authorize('view', $investment);

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
        $this->authorize('view', $investment);

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
        $this->authorize('view', $investment);

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

        // Check if user is authorized to view this investment
        $this->authorize('view', $investment);

        // Check if the investment has a price provider configured
        if (!$investment->investment_price_provider) {
            self::addSimpleErrorMessage(__('No price provider configured for this investment.'));
            return redirect()->back();
        }

        try {
            // Get latest known date of price date, so we can retrieve missing values
            $lastPrice = $investment->investmentPrices->last('date');
            $date = $lastPrice ? $lastPrice->date : Carbon::now()->subDays(30);

            $investment->getInvestmentPriceFromProvider($date);

            // Use the InvestmentService to recalculate the related accounts
            $investmentService = new InvestmentService();
            $investmentService->recalculateRelatedAccounts($investment);

            self::addSimpleSuccessMessage(__('Investment prices successfully downloaded from :date', ['date' => $date->toFormattedDateString()]));

        } catch (\Exception $e) {
            // Sanitize error message to remove API keys and sensitive data
            $errorMessage = $e->getMessage();
            
            // Remove API key from URL if present
            $errorMessage = preg_replace('/apikey=[A-Za-z0-9]+/', 'apikey=***', $errorMessage);
            
            // Remove file paths
            $errorMessage = preg_replace('/[A-Z]:\\\\[^\\s]+/', '***', $errorMessage);
            
            self::addSimpleErrorMessage(__('Failed to retrieve investment prices: :error', ['error' => $errorMessage]));
        }

        return redirect()->back();
    }

    /**
     * Import price history from Buy/Sell transactions for an investment
     *
     * @param Investment $investment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function importFromTrades(Investment $investment)
    {
        $this->authorize('view', $investment);

        // Dispatch the job after the response is sent to avoid timeout
        \App\Jobs\ImportInvestmentPricesFromTrades::dispatchAfterResponse($investment, auth()->id());

        self::addSimpleSuccessMessage(__('Price import from trades has been queued. This may take a few moments to complete.'));

        return redirect()->route('investment-price.list', $investment);
    }
}

