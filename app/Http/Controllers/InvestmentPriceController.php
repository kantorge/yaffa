<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\InvestmentPrice;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;

class InvestmentPriceController extends Controller
{
    public function retreiveInvestmentPriceAlphaVantage(Investment $investment, ?Carbon $from = null)
    {
        $refill = false;

        $date = Carbon::create('yesterday');
        if (!$from) {
            $from = Carbon::create('yesterday');
        }

        $client = new GuzzleClient();

        $res = $client->request('GET', 'https://www.alphavantage.co/query', [
            'query' => [
                'function' => 'TIME_SERIES_DAILY',
                'datatype' => 'json',
                'symbol' => $investment->symbol,
                'apikey' => env('ALPHA_VANTAGE_KEY'),
                'outputsize' => ($refill ? 'full' : 'compact'),
            ]
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
