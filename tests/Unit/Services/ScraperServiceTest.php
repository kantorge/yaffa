<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnsafeEndpointUrlException;
use App\Services\ScraperService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScraperServiceTest extends TestCase
{
    public function test_scrape_rejects_loopback_url_without_dispatching_request(): void
    {
        Http::fake();

        $service = new ScraperService();

        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessageIsOrContains('Endpoint URL must resolve to a public IP address.');

        try {
            $service->scrape('http://127.0.0.1/admin', '.price');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_scrape_rejects_link_local_metadata_url(): void
    {
        $service = new ScraperService();

        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessageIsOrContains('Endpoint URL must resolve to a public IP address.');

        $service->scrape('http://169.254.169.254/latest/meta-data/', 'body');
    }

    public function test_scrape_returns_matched_selector_text(): void
    {
        Http::fake([
            '8.8.8.8/*' => Http::response('<html><body><span class="price">123.45</span></body></html>'),
        ]);

        $service = new ScraperService();
        $result = $service->scrape('https://8.8.8.8/price', '.price');

        $this->assertSame([['price' => '123.45']], $result);
    }

    public function test_scrape_returns_empty_array_when_selector_does_not_match(): void
    {
        Http::fake([
            '8.8.8.8/*' => Http::response('<html><body><span class="other">123.45</span></body></html>'),
        ]);

        $service = new ScraperService();
        $result = $service->scrape('https://8.8.8.8/price', '.price');

        $this->assertSame([], $result);
    }
}
