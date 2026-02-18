<?php

namespace Tests\Unit\Services\InvestmentPriceProviders;

use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use App\Services\InvestmentPriceProviders\WebScrapingProvider;
use App\Services\ScraperService;
use Carbon\Carbon;
use Exception;
use Mockery;
use RoachPHP\ItemPipeline\Item;
use Tests\TestCase;

class WebScrapingProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetches_prices_successfully(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andReturn([new Item(['price' => 123.45])]);

        $provider = new WebScrapingProvider($scraperService);
        $prices = $provider->fetchPrices($investment);

        $this->assertCount(1, $prices);
        $this->assertEquals(123.45, $prices[0]['price']);
        $this->assertEquals(Carbon::yesterday()->format('Y-m-d'), $prices[0]['date']);
    }

    public function test_handles_empty_scraping_result(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andReturn([]);

        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Web scraping returned no results');

        $provider->fetchPrices($investment);
    }

    public function test_handles_missing_scrape_url(): void
    {
        $investment = $this->makeInvestment([
            'scrape_url' => null,
        ]);

        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Missing scrape URL');

        $provider->fetchPrices($investment);
    }

    public function test_handles_missing_scrape_selector(): void
    {
        $investment = $this->makeInvestment([
            'scrape_selector' => null,
        ]);

        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Missing scrape selector');

        $provider->fetchPrices($investment);
    }

    public function test_handles_invalid_price_format(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andReturn([new Item(['price' => 'not-a-number'])]);

        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Invalid price format');

        $provider->fetchPrices($investment);
    }

    public function test_handles_negative_price(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andReturn([new Item(['price' => -10.5])]);

        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Invalid price value');

        $provider->fetchPrices($investment);
    }

    public function test_handles_zero_price(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andReturn([new Item(['price' => 0])]);

        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Invalid price value');

        $provider->fetchPrices($investment);
    }

    public function test_handles_scraping_exception(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andThrow(new Exception('Connection failed'));

        $provider = new WebScrapingProvider($scraperService);

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Connection failed');

        $provider->fetchPrices($investment);
    }

    public function test_does_not_support_refill(): void
    {
        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $this->assertFalse($provider->supportsRefill());
    }

    public function test_get_name(): void
    {
        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $this->assertEquals('web_scraping', $provider->getName());
    }

    public function test_ignores_from_parameter(): void
    {
        $investment = $this->makeInvestment();

        $scraperService = Mockery::mock(ScraperService::class);
        $scraperService->shouldReceive('scrape')
            ->once()
            ->with($investment->scrape_url, $investment->scrape_selector)
            ->andReturn([new Item(['price' => 123.45])]);

        $provider = new WebScrapingProvider($scraperService);
        $prices = $provider->fetchPrices($investment, Carbon::parse('2020-01-01'));

        $this->assertEquals(Carbon::yesterday()->format('Y-m-d'), $prices[0]['date']);
    }

    public function test_get_display_name(): void
    {
        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $this->assertEquals('Web Scraping', $provider->getDisplayName());
    }

    public function test_get_description(): void
    {
        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $description = $provider->getDescription();
        $this->assertStringContainsString('Web scraping', $description);
        $this->assertStringContainsString('websites', $description);
    }

    public function test_get_instructions(): void
    {
        $scraperService = Mockery::mock(ScraperService::class);
        $provider = new WebScrapingProvider($scraperService);

        $instructions = $provider->getInstructions();
        $this->assertStringContainsString('URL', $instructions);
        $this->assertStringContainsString('selector', $instructions);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeInvestment(array $overrides = []): Investment
    {
        $attributes = array_merge([
            'symbol' => 'TEST',
            'scrape_url' => 'https://example.com/price',
            'scrape_selector' => '.price',
        ], $overrides);

        return new Investment($attributes);
    }
}
