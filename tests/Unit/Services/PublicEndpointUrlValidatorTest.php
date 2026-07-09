<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnsafeEndpointUrlException;
use App\Services\PublicEndpointUrlValidator;
use Tests\TestCase;

class PublicEndpointUrlValidatorTest extends TestCase
{
    public function test_allows_public_ip_literal(): void
    {
        $resolvedIps = PublicEndpointUrlValidator::assertPublic('https://8.8.8.8/path');

        $this->assertSame(['8.8.8.8'], $resolvedIps);
    }

    public function test_rejects_loopback_ip_literal(): void
    {
        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        PublicEndpointUrlValidator::assertPublic('http://127.0.0.1/admin');
    }

    public function test_rejects_ipv6_loopback_literal(): void
    {
        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        PublicEndpointUrlValidator::assertPublic('http://[::1]/admin');
    }

    public function test_rejects_private_range_ip_literal(): void
    {
        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        PublicEndpointUrlValidator::assertPublic('http://169.254.169.254/latest/meta-data/');
    }

    public function test_rejects_localhost_hostname(): void
    {
        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must resolve to a public IP address.');

        PublicEndpointUrlValidator::assertPublic('http://localhost:8080/');
    }

    public function test_rejects_url_without_host(): void
    {
        $this->expectException(UnsafeEndpointUrlException::class);
        $this->expectExceptionMessage('Endpoint URL must include a valid host.');

        PublicEndpointUrlValidator::assertPublic('not-a-url');
    }
}
