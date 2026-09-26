<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiter as RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RateLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rate_limiter_keys_by_user_id_not_shared_across_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $limiterCallback = app(RateLimiterService::class)->limiter('api');

        $requestA = Request::create('/api/v1/accounts', 'GET');
        $requestA->setUserResolver(fn () => $userA);

        $requestB = Request::create('/api/v1/accounts', 'GET');
        $requestB->setUserResolver(fn () => $userB);

        $limitA = $limiterCallback($requestA);
        $limitB = $limiterCallback($requestB);

        $this->assertNotSame($limitA->key, $limitB->key);
    }

    public function test_api_rate_limiter_falls_back_to_ip_when_unauthenticated(): void
    {
        $limiterCallback = app(RateLimiterService::class)->limiter('api');

        $requestA = Request::create('/api/v1/accounts', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $requestB = Request::create('/api/v1/accounts', 'GET', server: ['REMOTE_ADDR' => '10.0.0.2']);

        $limitA = $limiterCallback($requestA);
        $limitB = $limiterCallback($requestB);

        $this->assertNotSame($limitA->key, $limitB->key);
    }
}
