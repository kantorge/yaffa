<?php

namespace Tests\Unit\Services\InvestmentPriceProviders;

use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use App\Services\InvestmentPriceProviders\GenericApiProvider;
use Illuminate\Support\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GenericApiProviderTest extends TestCase
{
    public function test_fetch_prices_parses_items_and_normalizes_dates(): void
    {
        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/prices?symbol=AAPL',
                [
                    'query' => [
                        'market' => 'us',
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30,
                ]
            )
            ->willReturn(new Response(200, [], json_encode([
                'data' => [
                    [
                        'date' => '2026-06-06',
                        'close' => '123.45',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)));

        $provider = new GenericApiProvider($client);

        $investment = new Investment([
            'symbol' => 'AAPL',
        ]);
        $investment->provider_credentials = [
            'endpoint_url' => 'https://api.example.com/prices?symbol={symbol}',
            'headers_json' => '{"Accept":"application/json"}',
            'query_json' => '{"market":"us"}',
            'items_path' => 'data',
            'date_path' => 'date',
            'price_path' => 'close',
            'date_format' => 'Y-m-d',
        ];

        $prices = $provider->fetchPrices($investment, Carbon::create(2026, 6, 1));

        $this->assertSame([
            [
                'date' => '2026-06-06',
                'price' => 123.45,
            ],
        ], $prices);
    }

    public function test_validate_credentials_rejects_invalid_json_fields(): void
    {
        $provider = new GenericApiProvider($this->createMock(Client::class));

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Invalid JSON in headers_json');

        $provider->validateCredentials([
            'endpoint_url' => 'https://api.example.com/prices?symbol={symbol}',
            'date_path' => 'data.0.date',
            'price_path' => 'data.0.close',
            'headers_json' => '{invalid',
        ]);
    }

    public function test_fetch_prices_supports_parallel_date_and_price_arrays(): void
    {
        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode([
                'chart' => [
                    'result' => [
                        [
                            'timestamp' => [1717718400, 1717804800],
                            'indicators' => [
                                'quote' => [
                                    [
                                        'close' => [99.5, 101.2],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)));

        $provider = new GenericApiProvider($client);

        $investment = new Investment([
            'symbol' => 'AGGG.L',
        ]);
        $investment->provider_credentials = [
            'endpoint_url' => 'https://query1.finance.yahoo.com/v8/finance/chart/{symbol}',
            'date_values_path' => 'chart.result.0.timestamp',
            'price_values_path' => 'chart.result.0.indicators.quote.0.close',
            'date_format' => 'timestamp_seconds',
        ];

        $prices = $provider->fetchPrices($investment, Carbon::create(2024, 6, 7));

        $this->assertSame([
            [
                'date' => '2024-06-07',
                'price' => 99.5,
            ],
            [
                'date' => '2024-06-08',
                'price' => 101.2,
            ],
        ], $prices);
    }

    public function test_validate_credentials_allows_placeholders_in_endpoint_url(): void
    {
        $provider = new GenericApiProvider($this->createMock(Client::class));

        $provider->validateCredentials([
            'endpoint_url' => 'https://query1.finance.yahoo.com/v8/finance/chart/{symbol}',
            'date_values_path' => 'chart.result.0.timestamp',
            'price_values_path' => 'chart.result.0.indicators.quote.0.close',
            'date_format' => 'timestamp_seconds',
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_validate_credentials_rejects_localhost_endpoint(): void
    {
        $provider = new GenericApiProvider($this->createMock(Client::class));

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        $provider->validateCredentials([
            'endpoint_url' => 'http://localhost:8080/prices',
            'date_path' => 'data.0.date',
            'price_path' => 'data.0.close',
        ]);
    }

    public function test_fetch_prices_rejects_loopback_endpoint_before_request(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('request');

        $provider = new GenericApiProvider($client);

        $investment = new Investment([
            'symbol' => 'MSFT',
        ]);
        $investment->provider_credentials = [
            'endpoint_url' => 'http://127.0.0.1/prices/{symbol}',
            'date_path' => 'date',
            'price_path' => 'close',
        ];

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        $provider->fetchPrices($investment);
    }
}
