<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    /**
     * @return array<int, array{price: string}>
     */
    public function scrape(string $url, string $selector): array
    {
        PublicEndpointUrlValidator::assertPublic($url);

        $response = Http::withUserAgent(self::USER_AGENT)
            // Never follow redirects: a validated public host could redirect the
            // actual request to an internal address, bypassing the check above.
            ->withOptions(['allow_redirects' => false])
            ->get($url)
            ->throw();

        $nodes = (new Crawler($response->body()))->filter($selector);

        if ($nodes->count() === 0) {
            return [];
        }

        return [
            ['price' => $nodes->first()->text()],
        ];
    }
}
