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

        // Default cutoff: beginning of today minus 5 days for incremental fetches.
        // For refill, keep $from as null so all returned data is accepted.
        $cutoff = $from
            ? $from->copy()->startOfDay()
            : ($refill ? null : Carbon::now()->subDays(5)->startOfDay());

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($investment->symbol),
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

            foreach ($timestamps as $i => $ts) {
                $close = $closes[$i] ?? null;

                if ($close === null || $close <= 0) {
                    continue;
                }

                $day = Carbon::createFromTimestamp($ts)->startOfDay();

                if ($cutoff !== null && $day->lt($cutoff)) {
                    continue;
                }

                $prices[] = [
                    'date'  => $day->format('Y-m-d'),
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
            $statusCode = $e->getResponse()->getStatusCode();
            $description = null;

            try {
                $body = json_decode((string) $e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $description = $body['chart']['error']['description'] ?? null;
            } catch (\Throwable) {
            }

            // 429 (rate limit) and 403 (forbidden/blocked) are transient — caller should retry later.
            // Only 400/404 indicate a permanently invalid symbol.
            if ($statusCode === 429 || $statusCode === 403) {
                throw new PriceProviderException(
                    $description ?? "Yahoo Finance request blocked (HTTP {$statusCode})",
                    'yahoo_finance',
                    $investment->symbol,
                    $e
                );
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

    /**
     * No credentials are required for Yahoo Finance.
     */
    public function validateCredentials(array $credentials): void
    {
    }

    public function getName(): string
    {
        return 'yahoo_finance';
    }

    public function getDisplayName(): string
    {
        return __('Yahoo Finance');
    }

    public function getDescription(): string
    {
        return __('Yahoo Finance provides free price data for stocks and ETFs listed on major world exchanges. No API key required.');
    }

    public function getInstructions(): string
    {
        return __('Set the Symbol field to the Yahoo Finance ticker, e.g. AGGG.L for London Stock Exchange or ISAC.MI for Borsa Italiana. No API key required.');
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
