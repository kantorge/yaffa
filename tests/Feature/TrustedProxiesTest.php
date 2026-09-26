<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    protected function assertSecureFromProxy(string $remoteAddr, bool $expectedSecure): void
    {
        $request = Request::create('http://example.com/', 'GET');
        $request->server->set('REMOTE_ADDR', $remoteAddr);
        $request->headers->set('X-Forwarded-Proto', 'https');

        (new TrustProxies())->handle($request, fn ($req) => $req);

        $this->assertSame($expectedSecure, $request->isSecure());
    }

    public function test_https_is_detected_when_a_trusted_proxy_forwards_it(): void
    {
        Config::set('trustedproxy.proxies', ['10.0.0.1']);

        $this->assertSecureFromProxy('10.0.0.1', true);
    }

    public function test_forwarded_https_is_not_trusted_from_an_untrusted_proxy(): void
    {
        Config::set('trustedproxy.proxies', ['10.0.0.1']);

        $this->assertSecureFromProxy('203.0.113.5', false);
    }

    public function test_wildcard_trusts_the_calling_proxy(): void
    {
        Config::set('trustedproxy.proxies', '*');

        $this->assertSecureFromProxy('198.51.100.7', true);
    }

    public function test_a_specific_trusted_proxy_ip_works(): void
    {
        Config::set('trustedproxy.proxies', ['192.0.2.10']);

        $this->assertSecureFromProxy('192.0.2.10', true);
        $this->assertSecureFromProxy('192.0.2.11', false);
    }

    public function test_multiple_proxies_including_a_cidr_range_are_trusted(): void
    {
        Config::set('trustedproxy.proxies', ['10.0.0.1', '192.168.1.0/24']);

        $this->assertSecureFromProxy('10.0.0.1', true);
        $this->assertSecureFromProxy('192.168.1.55', true);
        $this->assertSecureFromProxy('192.168.2.1', false);
    }

    public function test_trusted_proxy_is_honored_on_stateful_api_requests(): void
    {
        Config::set('trustedproxy.proxies', ['10.0.0.1']);

        $request = Request::create('http://example.com/api/v1/ping', 'GET');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-Proto', 'https');

        (new TrustProxies())->handle($request, fn ($req) => $req);

        $this->assertTrue($request->isSecure());
    }
}
