<?php

namespace App\Providers;

use App\Services\InvestmentPriceProviderRegistry;
use App\Services\InvestmentPriceProviders\AlphaVantageProvider;
use App\Services\InvestmentPriceProviders\GenericApiProvider;
use App\Services\InvestmentPriceProviders\WebScrapingProvider;
use App\Services\ScraperService;
use GuzzleHttp\Client;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class InvestmentPriceProviderServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     * This method is only called when InvestmentPriceProviderRegistry is actually needed.
     */
    public function register(): void
    {
        $this->app->singleton(InvestmentPriceProviderRegistry::class, function ($app) {
            $registry = new InvestmentPriceProviderRegistry();

            // Register Alpha Vantage provider
            $registry->register(
                'alpha_vantage',
                new AlphaVantageProvider(new Client())
            );

            // Register Web Scraping provider
            $registry->register(
                'web_scraping',
                new WebScrapingProvider(new ScraperService())
            );

            // Register Generic API provider (advanced, user-configured)
            $registry->register(
                'generic_api',
                new GenericApiProvider(new Client([
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'verify' => true,
                    'http_errors' => true,
                ]))
            );

            return $registry;
        });
    }

    /**
     * Get the services provided by the provider.
     * This tells Laravel which services this provider offers, so it knows when to load it.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            InvestmentPriceProviderRegistry::class,
        ];
    }
}
