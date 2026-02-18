<?php

namespace Tests\Unit\Services\InvestmentPriceProviders;

use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use App\Services\InvestmentPriceProviders\AlphaVantageProvider;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class AlphaVantageProviderTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        return new Client(['handler' => $handlerStack]);
    }

    public function test_fetches_prices_successfully(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $responseBody = json_encode([
            'Time Series (Daily)' => [
                '2024-01-15' => ['4. close' => '150.25'],
                '2024-01-14' => ['4. close' => '149.50'],
                '2024-01-13' => ['4. close' => '148.75'],
            ],
        ]);

        $client = $this->createMockClient([
            new Response(200, [], $responseBody),
        ]);

        $provider = new AlphaVantageProvider($client);
        $prices = $provider->fetchPrices($investment, Carbon::parse('2024-01-13'));

        $this->assertCount(3, $prices);
        $this->assertEquals('2024-01-15', $prices[0]['date']);
        $this->assertEquals(150.25, $prices[0]['price']);
    }

    public function test_filters_prices_by_from_date(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $responseBody = json_encode([
            'Time Series (Daily)' => [
                '2024-01-15' => ['4. close' => '150.25'],
                '2024-01-14' => ['4. close' => '149.50'],
                '2024-01-12' => ['4. close' => '148.00'],
            ],
        ]);

        $client = $this->createMockClient([
            new Response(200, [], $responseBody),
        ]);

        $provider = new AlphaVantageProvider($client);
        $prices = $provider->fetchPrices($investment, Carbon::parse('2024-01-14'));

        $this->assertCount(2, $prices);
        $this->assertEquals('2024-01-15', $prices[0]['date']);
        $this->assertEquals('2024-01-14', $prices[1]['date']);
    }

    public function test_handles_guzzle_exception(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $client = $this->createMockClient([
            new ConnectException('Connection timeout', new Request('GET', 'test')),
        ]);

        $provider = new AlphaVantageProvider($client);

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Failed to fetch prices from Alpha Vantage');

        $provider->fetchPrices($investment);
    }

    public function test_handles_json_exception(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $client = $this->createMockClient([
            new Response(200, [], 'invalid json{'),
        ]);

        $provider = new AlphaVantageProvider($client);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Invalid JSON response from Alpha Vantage');

        $provider->fetchPrices($investment);
    }

    public function test_handles_missing_time_series(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $responseBody = json_encode([
            'Meta Data' => ['symbol' => 'AAPL'],
        ]);

        $client = $this->createMockClient([
            new Response(200, [], $responseBody),
        ]);

        $provider = new AlphaVantageProvider($client);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Missing Time Series data');

        $provider->fetchPrices($investment);
    }

    public function test_handles_api_error_message(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'INVALID',
        ]);

        $responseBody = json_encode([
            'Error Message' => 'Invalid API call. Please retry or visit the documentation',
        ]);

        $client = $this->createMockClient([
            new Response(200, [], $responseBody),
        ]);

        $provider = new AlphaVantageProvider($client);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Alpha Vantage API error');

        $provider->fetchPrices($investment);
    }

    public function test_handles_rate_limit(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $responseBody = json_encode([
            'Note' => 'Thank you for using Alpha Vantage! Our standard API call frequency is 5 calls per minute.',
        ]);

        $client = $this->createMockClient([
            new Response(200, [], $responseBody),
        ]);

        $provider = new AlphaVantageProvider($client);

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Alpha Vantage rate limit');

        $provider->fetchPrices($investment);
    }

    public function test_skips_entries_with_missing_close_price(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'AAPL',
        ]);

        $responseBody = json_encode([
            'Time Series (Daily)' => [
                '2024-01-15' => ['4. close' => '150.25'],
                '2024-01-14' => ['1. open' => '149.00'], // Missing close price
                '2024-01-13' => ['4. close' => '148.75'],
            ],
        ]);

        $client = $this->createMockClient([
            new Response(200, [], $responseBody),
        ]);

        $provider = new AlphaVantageProvider($client);
        $prices = $provider->fetchPrices($investment, Carbon::parse('2024-01-13'));

        $this->assertCount(2, $prices);
        $this->assertEquals('2024-01-15', $prices[0]['date']);
        $this->assertEquals('2024-01-13', $prices[1]['date']);
    }

    public function test_supports_refill(): void
    {
        $client = $this->createMockClient([]);
        $provider = new AlphaVantageProvider($client);

        $this->assertTrue($provider->supportsRefill());
    }

    public function test_get_name(): void
    {
        $client = $this->createMockClient([]);
        $provider = new AlphaVantageProvider($client);

        $this->assertEquals('alpha_vantage', $provider->getName());
    }

    public function test_get_display_name(): void
    {
        $client = $this->createMockClient([]);
        $provider = new AlphaVantageProvider($client);

        $this->assertEquals('Alpha Vantage', $provider->getDisplayName());
    }

    public function test_get_description(): void
    {
        $client = $this->createMockClient([]);
        $provider = new AlphaVantageProvider($client);

        $description = $provider->getDescription();
        $this->assertStringContainsString('Alpha Vantage', $description);
        $this->assertStringContainsString('API', $description);
    }

    public function test_get_instructions(): void
    {
        $client = $this->createMockClient([]);
        $provider = new AlphaVantageProvider($client);

        $instructions = $provider->getInstructions();
        $this->assertStringContainsString('API key', $instructions);
    }
}
