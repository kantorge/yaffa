<?php

namespace App\Services\InvestmentPriceProviders;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

class YahooFinanceProvider implements InvestmentPriceProvider
{
    public function __construct(private Client $httpClient)
    {
    }

    public function fetchPrices(Investment $investment, ?Carbon $from = null, bool $refill = false): array
    {
        $range = $refill ? '2y' : '5d';

        try {
            $response = $this->httpClient->request(
                'GET',
                "https://query1.finance.yahoo.com/v8/finance/chart/{$investment->symbol}",
                [
                    'query' => [
                        'interval' => '1d',
                        'range'    => $range,
                    ],
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (compatible; YAFFA/1.0)',
                    ],
                    'timeout' => 30,
                ]
            );

            $body = (string) $response->getBody();
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            $result = $data['chart']['result'][0] ?? null;

            if (! $result) {
                $error = $data['chart']['error']['description'] ?? 'Unknown error';
                throw new InvalidPriceDataException(
                    "Yahoo Finance error: {$error}",
                    'yahoo_finance',
                    $investment->symbol
                );
            }

            $timestamps = $result['timestamp'] ?? [];
            $closes = $result['indicators']['quote'][0]['close'] ?? [];

            if (empty($timestamps) || empty($closes)) {
                throw new InvalidPriceDataException(
                    'Yahoo Finance returned no price data',
                    'yahoo_finance',
                    $investment->symbol
                );
            }

            $prices = [];
            $from = $from ?? Carbon::now()->subDays(5);

            foreach ($timestamps as $i => $ts) {
                $date = Carbon::createFromTimestamp($ts)->format('Y-m-d');
                $close = $closes[$i] ?? null;

                if ($close === null || $close <= 0) {
                    continue;
                }

                if (Carbon::createFromFormat('Y-m-d', $date)->lt($from)) {
                    continue;
                }

                $prices[] = [
                    'date'  => $date,
                    'price' => (float) $close,
                ];
            }

            if (empty($prices)) {
                throw new InvalidPriceDataException(
                    'Yahoo Finance: no prices found in the requested date range',
                    'yahoo_finance',
                    $investment->symbol
                );
            }

            return $prices;

        } catch (InvalidPriceDataException $e) {
            throw $e;
        } catch (ClientException $e) {
            $description = null;
            try {
                $body = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $description = $body['chart']['error']['description'] ?? null;
            } catch (\Throwable) {
            }
            throw new InvalidPriceDataException(
                $description ?? 'Yahoo Finance error: symbol not found or invalid',
                'yahoo_finance',
                $investment->symbol
            );
        } catch (GuzzleException $e) {
            throw new PriceProviderException(
                "Yahoo Finance request failed: {$e->getMessage()}",
                'yahoo_finance',
                $investment->symbol,
                $e
            );
        } catch (JsonException $e) {
            throw new PriceProviderException(
                "Yahoo Finance invalid JSON response: {$e->getMessage()}",
                'yahoo_finance',
                $investment->symbol,
                $e
            );
        }
    }

    public function validateCredentials(array $credentials): void
    {
        // No credentials needed
    }

    public function getName(): string
    {
        return 'yahoo_finance';
    }

    public function getDisplayName(): string
    {
        return 'Yahoo Finance';
    }

    public function getDescription(): string
    {
        return 'Yahoo Finance provides free price data for stocks and ETFs listed on major world exchanges. No API key required.';
    }

    public function getInstructions(): string
    {
        return 'Set the Symbol field to the Yahoo Finance ticker, e.g. AGGG.L for London Stock Exchange or ISAC.MI for Borsa Italiana. No API key required.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvestmentSettingsSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => [],
            'properties' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserSettingsSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => [],
            'properties' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRateLimitPolicy(): array
    {
        return [
            'perSecond'    => null,
            'perMinute'    => 10,
            'perDay'       => 1000,
            'reserve'      => 0,
            'overrideable' => false,
        ];
    }

    public function supportsHistoricalSync(): bool
    {
        return true;
    }
}
