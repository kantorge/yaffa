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

        $providerSettings = is_array($investment->provider_settings)
            ? $investment->provider_settings
            : [];
        $scrapeUrl = isset($providerSettings['url']) && is_string($providerSettings['url'])
            ? $providerSettings['url']
            : null;
        $scrapeSelector = isset($providerSettings['selector']) && is_string($providerSettings['selector'])
            ? $providerSettings['selector']
            : null;

        if (empty($scrapeUrl)) {
            throw new InvalidPriceDataException(
                'Missing scrape URL for web scraping',
                'web_scraping',
                $investment->symbol
            );
        }

        if (empty($scrapeSelector)) {
            throw new InvalidPriceDataException(
                'Missing scrape selector for web scraping',
                'web_scraping',
                $investment->symbol
            );
        }

        try {
            $result = $this->scraperService->scrape(
                $scrapeUrl,
                $scrapeSelector
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
                'url' => $scrapeUrl,
                'selector' => $scrapeSelector,
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

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(array $credentials): void
    {
        // No account-level credentials are required for this provider.
    }

    public function getName(): string
    {
        return 'web_scraping';
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

    /**
     * @return array<string, mixed>
     */
    public function getInvestmentSettingsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url', 'selector'],
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'format' => 'url',
                    'label' => __('URL'),
                    'helpText' => __('Public URL of the page where the latest investment price is shown.'),
                ],
                'selector' => [
                    'type' => 'string',
                    'label' => __('CSS selector'),
                    'minLength' => 1,
                    'helpText' => __('CSS selector that identifies the element containing the price.'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserSettingsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRateLimitPolicy(): array
    {
        return [
            'perSecond' => null,
            'perMinute' => 30,
            'perDay' => 10000,
            'reserve' => 0,
            'overrideable' => false,
        ];
    }

    public function supportsHistoricalSync(): bool
    {
        return false;
    }
}
