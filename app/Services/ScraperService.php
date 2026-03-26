<?php

namespace App\Services;

use App\Spiders\InvestmentPriceScraper;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

class ScraperService
{
    /**
     * @return array<int, ItemInterface>
     */
    public function scrape(string $url, string $selector): array
    {
        return Roach::collectSpider(
            InvestmentPriceScraper::class,
            new Overrides(
                startUrls: [$url],
            ),
            [
                'selector' => $selector,
            ]
        );
    }
}
