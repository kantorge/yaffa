<?php

namespace App\Services\InvestmentPriceProviders;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use JsonException;

class AlphaVantageProvider implements InvestmentPriceProvider
{
    public function __construct(private Client $httpClient)
    {
    }

    public function fetchPrices(Investment $investment, ?Carbon $from = null, bool $refill = false): array
    {
        // Get 3 days data by default, assuming that scheduler is running
        if (! $from) {
            $from = Carbon::now()->subDays(3);
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://www.alphavantage.co/query',
                [
                    'query' => [
                        'function' => 'TIME_SERIES_DAILY',
                        'datatype' => 'json',
                        'symbol' => $investment->symbol,
                        'apikey' => config('yaffa.alpha_vantage_key'),
                        'outputsize' => ($refill ? 'full' : 'compact'),
                    ],
                    'timeout' => 30,
                ]
            );

            $body = (string) $response->getBody();
            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

            // Validate response structure
            if (! isset($data->{'Time Series (Daily)'})) {
                // Check for error messages from API
                if (isset($data->{'Error Message'})) {
                    throw new InvalidPriceDataException(
                        "Alpha Vantage API error: {$data->{'Error Message'}}",
                        'alpha_vantage',
                        $investment->symbol
                    );
                }

                if (isset($data->Note)) {
                    throw new PriceProviderException(
                        "Alpha Vantage rate limit: {$data->Note}",
                        'alpha_vantage',
                        $investment->symbol
                    );
                }

                throw new InvalidPriceDataException(
                    'Missing Time Series data in Alpha Vantage response',
                    'alpha_vantage',
                    $investment->symbol
                );
            }

            $prices = [];
            foreach ($data->{'Time Series (Daily)'} as $date => $dailyData) {
                if ($from->gt(Carbon::createFromFormat('Y-m-d', $date))) {
                    continue;
                }

                // Validate that closing price exists
                if (! isset($dailyData->{'4. close'})) {
                    Log::warning("Missing closing price for {$investment->symbol} on {$date}");

                    continue;
                }

                $prices[] = [
                    'date' => $date,
                    'price' => (float) $dailyData->{'4. close'},
                ];
            }

            return $prices;
        } catch (GuzzleException $e) {
            Log::error("AlphaVantage HTTP error for {$investment->symbol}", [
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw new PriceProviderException(
                "Failed to fetch prices from Alpha Vantage: {$e->getMessage()}",
                'alpha_vantage',
                $investment->symbol,
                $e
            );
        } catch (JsonException $e) {
            Log::error("AlphaVantage JSON parsing error for {$investment->symbol}", [
                'exception' => $e->getMessage(),
            ]);

            throw new InvalidPriceDataException(
                "Invalid JSON response from Alpha Vantage: {$e->getMessage()}",
                'alpha_vantage',
                $investment->symbol,
                $e
            );
        }
    }

    public function getName(): string
    {
        return 'alpha_vantage';
    }

    public function supportsRefill(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return __('Alpha Vantage');
    }

    public function getDescription(): string
    {
        return __('Alpha Vantage is a leading provider of free APIs for historical and real-time data on stocks, forex (FX), and digital/crypto currencies.');
    }

    public function getInstructions(): string
    {
        return __('To use Alpha Vantage, you need to get an API key. The key is free, but you need to register on their website.');
    }
}
