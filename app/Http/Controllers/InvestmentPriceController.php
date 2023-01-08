<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentPriceRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function list(Investment $investment)
    {
        /**
         * @get('/investment-price/list/{investment}')
         * @name('investment-price.list')
         * @middlewares('web', 'auth', 'verified')
         */
        $this->authorize('view', $investment);

        $pricesOrdered = DB::table('investment_prices')
            ->select('id', 'date', 'price')
            ->where('investment_id', $investment->id)
            ->orderBy('date')
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
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

    public function create(Request $request)
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

    public function store(InvestmentPriceRequest $request)
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
     * @return \Illuminate\View\View
     */
    public function edit(InvestmentPrice $investmentPrice)
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

    public function update(InvestmentPriceRequest $request)
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
     * @return \Illuminate\Http\Response
     */
    public function destroy(InvestmentPrice $investmentPrice)
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

    public function retreiveInvestmentPriceAlphaVantage(Investment $investment, ?Carbon $from = null)
    {
        /**
         * @get('/investment-price/get/{investment}/{from?}')
         * @name('investment-price.retreive')
         * @middlewares('web', 'auth', 'verified')
         */
        $refill = false;

        $client = new GuzzleClient();

        $response = $client->request('GET', 'https://www.alphavantage.co/query', [
            'query' => [
                'function' => 'TIME_SERIES_DAILY_ADJUSTED',
                'datatype' => 'json',
                'symbol' => $investment->symbol,
                'apikey' => config('yaffa.alpha_vantage_key'),
                'outputsize' => ($refill ? 'full' : 'compact'),
            ],
        ]);

        $obj = json_decode($response->getBody());

        foreach ($obj->{'Time Series (Daily)'} as $date => $daily_data) {
            // If the date is before the from date, skip it
            if ($from && $from->gt(Carbon::createFromFormat('Y-m-d', $date))) {
                continue;
            }

            InvestmentPrice::updateOrCreate(
                [
                    'investment_id' => $investment->id,
                    'date' => $date,
                ],
                [
                    'price' => $daily_data->{'4. close'},
                ]
            );
        }

        return redirect()->back();
    }
}
