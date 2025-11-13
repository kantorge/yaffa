<?php

namespace App\Spiders;

use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Downloader\Middleware\UserAgentMiddleware;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;

class WisealphaInvestmentPriceScraper extends BasicSpider
{
    public array $startUrls = [];

    public function __construct()
    {
        parent::__construct();
    }

    // Declare download middleware
    public array $downloaderMiddleware = [
        RequestDeduplicationMiddleware::class,
        [UserAgentMiddleware::class, ["userAgent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36"]],
    ];
    
    // Configure concurrency and request options
    public int $concurrency = 1;
    public int $requestDelay = 1;

    public function parse(Response $response): \Generator
    {
        
        // Extract the buyPrice from JavaScript variables in the page
        $html = $response->getBody();
        
        // Log basic response details
        \Log::info('WiseAlpha spider response details', [
            'url' => $response->getUri(),
            'content_length' => strlen($html),
            'content_type' => $response->getHeader('content-type'),
            'has_buyPrice_text' => str_contains($html, 'buyPrice'),
            'html_preview' => substr($html, 0, 500) // First 500 chars
        ]);
        
        // Look for buyPrice in various possible JavaScript patterns
        $patterns = [
            '/buyPrice\s*[:=]\s*(\d+(?:\.\d+)?)/i',
            '/["\']buyPrice["\']\s*[:=]\s*(\d+(?:\.\d+)?)/i',
            '/"buyPrice"\s*:\s*(\d+(?:\.\d+)?)/i',
            '/var\s+buyPrice\s*=\s*(\d+(?:\.\d+)?)/i',
            // Additional patterns for WiseAlpha structure
            '/buyPrice["\']\s*:\s*(\d+(?:\.\d+)?)/i',
            '/window\.buyPrice\s*=\s*(\d+(?:\.\d+)?)/i',
            '/const\s+buyPrice\s*=\s*(\d+(?:\.\d+)?)/i',
            '/let\s+buyPrice\s*=\s*(\d+(?:\.\d+)?)/i',
        ];
        
        $priceValue = null;
        $matchedPattern = null;
        
        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $priceValue = (float) $matches[1];
                $matchedPattern = $pattern;
                break;
            }
        }
        
        // If no buyPrice found, try to find price in data attributes or other locations
        if ($priceValue === null) {
            // Look for data-price attributes
            if (preg_match('/data-price\s*=\s*["\']?(\d+(?:\.\d+)?)["\']?/i', $html, $matches)) {
                $priceValue = (float) $matches[1];
                $matchedPattern = 'data-price attribute';
            }
        }
        
        // If still no price found, log debugging info and return null
        if ($priceValue === null) {
            \Log::warning('WiseAlpha spider could not find buyPrice', [
                'url' => $response->getUri(),
                'html_length' => strlen($html),
                'contains_buyPrice' => str_contains($html, 'buyPrice'),
                'html_excerpt' => substr($html, 0, 1000) // First 1000 chars for debugging
            ]);
            return;
        }
        
        // Log successful price extraction
        \Log::info('WiseAlpha spider extracted price', [
            'url' => $response->getUri(),
            'raw_price' => $priceValue,
            'final_price' => $priceValue / 100,
            'matched_pattern' => $matchedPattern
        ]);
        
        // Divide by 100 as requested
        $priceValue = $priceValue / 100;

        // Return the price data
        yield $this->item([
            'price' => $priceValue,
        ]);
    }
}