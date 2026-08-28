<?php

namespace Tests\Unit;

use App\Jobs\GetInvestmentPrices;
use App\Jobs\Middleware\SkipWhenRateLimited;
use App\Models\Investment;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use ReflectionClass;
use Tests\TestCase;

class GetInvestmentPricesTest extends TestCase
{
    public function test_job_has_retry_configuration(): void
    {
        $job = new GetInvestmentPrices(new Investment());

        $reflection = new ReflectionClass($job);

        $this->assertSame(0, $reflection->getAttributes(Tries::class)[0]->newInstance()->tries);
        $this->assertSame(3, $reflection->getAttributes(MaxExceptions::class)[0]->newInstance()->maxExceptions);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    public function test_job_middleware_contains_without_overlapping_and_rate_limiter(): void
    {
        $investment = new Investment();
        $investment->id = 123;

        $job = new GetInvestmentPrices($investment, [
            'bucketKey' => 'investment-price-provider:alpha_vantage:user:1',
            'perMinute' => 5,
            'perDay' => 500,
        ]);

        $middlewares = $job->middleware();

        $this->assertCount(2, $middlewares);
        $this->assertInstanceOf(WithoutOverlapping::class, $middlewares[0]);
        $this->assertInstanceOf(SkipWhenRateLimited::class, $middlewares[1]);
    }
}
