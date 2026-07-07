<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnsafeEndpointUrlException;
use App\Services\ScraperService;
use Tests\TestCase;

class ScraperServiceTest extends TestCase
{
    public function test_scrape_rejects_loopback_url_without_dispatching_spider(): void
    {
        $service = new ScraperService();

        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        $service->scrape('http://127.0.0.1/admin', '.price');
    }

    public function test_scrape_rejects_link_local_metadata_url(): void
    {
        $service = new ScraperService();

        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        $service->scrape('http://169.254.169.254/latest/meta-data/', 'body');
    }
}
