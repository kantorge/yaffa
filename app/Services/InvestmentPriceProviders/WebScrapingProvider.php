<?php

namespace App\Services\InvestmentPriceProviders;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\InvalidPriceDataException;
use App\Exceptions\PriceProviderException;
use App\Models\Investment;
use App\Services\ScraperService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class WebScrapingProvider implements InvestmentPriceProvider
{
    public function __construct(private ScraperService $scraperService)
    {
    }

    public function fetchPrices(Investment $investment, ?Carbon $from = null, bool $refill = false): array
    {
        // This provider ignores the $from and $refill parameters, as it looks for the latest price,
        // which is assumed to be the one applying to the previous day

        // Validate required configuration
        if (empty($investment->scrape_url)) {
            throw new InvalidPriceDataException(
                'Missing scrape URL for web scraping',
                'web_scraping',
                $investment->symbol
            );
        }

        if (empty($investment->scrape_selector)) {
            throw new InvalidPriceDataException(
                'Missing scrape selector for web scraping',
                'web_scraping',
                $investment->symbol
            );
        }

        try {
            $result = $this->scraperService->scrape(
                $investment->scrape_url,
                $investment->scrape_selector
            );

            if (empty($result)) {
                throw new InvalidPriceDataException(
                    'Web scraping returned no results - selector may be invalid or page structure changed',
                    'web_scraping',
                    $investment->symbol
                );
            }

            $priceValue = $result[0]->get('price');

            // Validate price format
            if (! is_numeric($priceValue)) {
                throw new InvalidPriceDataException(
                    "Invalid price format from web scraping: {$priceValue}",
                    'web_scraping',
                    $investment->symbol
                );
            }

            $price = (float) $priceValue;

            if ($price <= 0) {
                throw new InvalidPriceDataException(
                    "Invalid price value from web scraping: {$price}",
                    'web_scraping',
                    $investment->symbol
                );
            }

            return [
                [
                    'date' => Carbon::yesterday()->format('Y-m-d'),
                    'price' => $price,
                ],
            ];
        } catch (InvalidPriceDataException $e) {
            // Re-throw our custom exceptions
            throw $e;
        } catch (Exception $e) {
            Log::error("Web scraping failed for {$investment->symbol}", [
                'url' => $investment->scrape_url,
                'selector' => $investment->scrape_selector,
                'exception' => $e->getMessage(),
            ]);

            throw new PriceProviderException(
                "Web scraping failed: {$e->getMessage()}",
                'web_scraping',
                $investment->symbol,
                $e
            );
        }
    }

    public function getName(): string
    {
        return 'web_scraping';
    }

    public function supportsRefill(): bool
    {
        return false;
    }

    public function getDisplayName(): string
    {
        return __('Web Scraping');
    }

    public function getDescription(): string
    {
        return __('Web scraping is a technique to extract data from websites. It is a common method to get data from websites that do not provide APIs.');
    }

    public function getInstructions(): string
    {
        return __('To use web scraping, you need to provide a URL and a CSS selector to extract the price from the website.');
    }
}
