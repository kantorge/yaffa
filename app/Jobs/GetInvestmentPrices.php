<?php

namespace App\Jobs;

use App\Jobs\Middleware\SkipWhenRateLimited;
use App\Models\Investment;
use App\Services\InvestmentPriceProviderContextResolver;
use App\Services\InvestmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class GetInvestmentPrices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public Investment $investment;

    /**
     * @var array<string, int|string|null>|null
     */
    public ?array $rateLimitPolicy;

    /**
     * Create a new job instance.
     */
    /**
     * @param  array<string, int|string|null>|null  $rateLimitPolicy
     */
    public function __construct(Investment $investment, ?array $rateLimitPolicy = null)
    {
        $this->investment = $investment;
        $this->rateLimitPolicy = $rateLimitPolicy;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('investment-price:' . $this->investment->id))->releaseAfter(30),
            new SkipWhenRateLimited('investment-price-provider'),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function getRateLimitPolicy(InvestmentPriceProviderContextResolver $contextResolver): array
    {
        if (is_array($this->rateLimitPolicy)) {
            return $this->rateLimitPolicy;
        }

        $context = $contextResolver->resolve($this->investment);

        return $context['rate_limit_policy'];
    }

    /**
     * Execute the job.
     */
    public function handle(InvestmentService $investmentService): void
    {
        $investmentService->markPriceFetchAttempted($this->investment);

        try {
            // Fetch and save investment prices from provider.
            // Recalculation of related account summaries is triggered automatically
            // via the InvestmentPricesUpdated event fired inside fetchAndSavePrices.
            $investmentService->fetchAndSavePrices($this->investment);
        } catch (Throwable $throwable) {
            $investmentService->markPriceFetchFailed($this->investment, $throwable->getMessage());

            throw $throwable;
        }

        $investmentService->markPriceFetchSucceeded($this->investment);
    }

    /**
     * Allow retries until end of day so rate-limited releases never exhaust a finite attempt counter.
     * Actual provider exceptions are still capped at $maxExceptions.
     */
    public function retryUntil(): Carbon
    {
        return Carbon::now()->endOfDay();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
