<?php

namespace App\Spiders;

use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Downloader\Middleware\UserAgentMiddleware;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use Generator;

class InvestmentPriceScraper extends BasicSpider
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

    public function parse(Response $response): Generator
    {
        // Extract the price from the response
        $priceText = $response->filter($this->context['selector'])->text();

        // Remove any non-numeric characters from the price
        $priceText = preg_replace('/[^0-9.]/', '', $priceText);

        // Convert to float
        $priceValue = (float) $priceText;

        // We expect only one price, so we return only one item
        yield $this->item([
            'price' => $priceValue,
        ]);
    }
}
