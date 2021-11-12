<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentPriceRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use JavaScript;

class InvestmentPriceController extends Controller
{

    public function list(Investment $investment)
    {
        // Pass data for DataTables
        JavaScript::put([
            'prices' => $investment->investmentPrices,
        ]);

        return view(
            'investment-prices.list',
            [
                'investment' => $investment,
                'prices' => $investment->investmentPrices
            ]
        );
    }

    public function create(Request $request)
    {
        $investment = Investment::find($request->get('investment'));

        return view(
            'investment-prices.form',
            [
                'investment' => $investment,
            ]
        );
    }

    public function store(InvestmentPriceRequest $request)
    {
        $validated = $request->validated();

        InvestmentPrice::create($validated);

        self::addSimpleSuccessMessage('Investment price added');

        return redirect()->route('investment-price.list', $request->investment_id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  InvestmentPrice  $investmentPrice
     * @return \Illuminate\View\View
     */
    public function edit(InvestmentPrice $investmentPrice)
    {
        return view(
            'investment-prices.form',
            [
                'investment' => $investmentPrice->investment,
                'investmentPrice'=> $investmentPrice
            ]
        );
    }

    public function update(InvestmentPriceRequest $request)
    {
        $validated = $request->validated();

        InvestmentPrice::find($request->input('id'))
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage('Investment price updated');

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
        $investmentPrice->delete();

        self::addSimpleSuccessMessage('Investment price deleted');

        return redirect()->back();
    }

    public function retreiveInvestmentPriceAlphaVantage(Investment $investment, ?Carbon $from = null)
    {
        $refill = false;

        $date = Carbon::create('yesterday');
        if (! $from) {
            $from = Carbon::create('yesterday');
        }

        $client = new GuzzleClient();

        $res = $client->request('GET', 'https://www.alphavantage.co/query', [
            'query' => [
                'function' => 'TIME_SERIES_DAILY',
                'datatype' => 'json',
                'symbol' => $investment->symbol,
                'apikey' => config('yaffa.alpha_vantage_key'),
                'outputsize' => ($refill ? 'full' : 'compact'),
            ],
        ]);

        $obj = json_decode($res->getBody());

        foreach ($obj->{'Time Series (Daily)'} as $date => $daily_data) {
            /*
            // Skip item, if older than latest data, and no refill is needed
            if (!$refill && Carbon::parse($date)->lessThanOrEqualTo($data['latestDate'])) {
                continue;
            }

            // Skip item, if older than first transaction data, even if refill is needed
            if (Carbon::parse($date)->lessThanOrEqualTo($data['firstDate'])) {
                continue;
            }
            */

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
