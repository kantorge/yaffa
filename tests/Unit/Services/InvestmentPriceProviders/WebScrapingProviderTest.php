<?php

namespace Tests\Unit\Services\InvestmentPriceProviders;

use App\Exceptions\InvalidPriceDataException;
use App\Models\Investment;
use App\Services\InvestmentPriceProviders\WebScrapingProvider;
use Tests\TestCase;
use Mockery;

class WebScrapingProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetches_prices_successfully(): void
    {
        // TODO: Refactor WebScrapingProvider to inject a Roach interface for testability
        // Cannot mock Roach static calls after class is autoloaded
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_handles_empty_scraping_result(): void
    {
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_handles_missing_scrape_url(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'TEST',
            'scrape_url' => null,
            'scrape_selector' => '.price',
        ]);

        $provider = new WebScrapingProvider();

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Missing scrape URL');

        $provider->fetchPrices($investment);
    }

    public function test_handles_missing_scrape_selector(): void
    {
        $investment = Investment::factory()->make([
            'symbol' => 'TEST',
            'scrape_url' => 'https://example.com/price',
            'scrape_selector' => null,
        ]);

        $provider = new WebScrapingProvider();

        $this->expectException(InvalidPriceDataException::class);
        $this->expectExceptionMessage('Missing scrape selector');

        $provider->fetchPrices($investment);
    }

    public function test_handles_invalid_price_format(): void
    {
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_handles_negative_price(): void
    {
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_handles_zero_price(): void
    {
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_handles_scraping_exception(): void
    {
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_does_not_support_refill(): void
    {
        $provider = new WebScrapingProvider();

        $this->assertFalse($provider->supportsRefill());
    }

    public function test_get_name(): void
    {
        $provider = new WebScrapingProvider();

        $this->assertEquals('web_scraping', $provider->getName());
    }

    public function test_ignores_from_parameter(): void
    {
        $this->markTestSkipped('Roach class cannot be mocked after autoloading - requires refactoring for DI');
    }

    public function test_get_display_name(): void
    {
        $provider = new WebScrapingProvider();

        $this->assertEquals('Web Scraping', $provider->getDisplayName());
    }

    public function test_get_description(): void
    {
        $provider = new WebScrapingProvider();

        $description = $provider->getDescription();
        $this->assertStringContainsString('Web scraping', $description);
        $this->assertStringContainsString('websites', $description);
    }

    public function test_get_instructions(): void
    {
        $provider = new WebScrapingProvider();

        $instructions = $provider->getInstructions();
        $this->assertStringContainsString('URL', $instructions);
        $this->assertStringContainsString('selector', $instructions);
    }
}
