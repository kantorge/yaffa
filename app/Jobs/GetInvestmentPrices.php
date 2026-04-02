<?php

namespace App\Jobs;

use App\Models\Investment;
use App\Services\InvestmentPriceProviderContextResolver;
use App\Services\InvestmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GetInvestmentPrices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

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
            new RateLimited('investment-price-provider'),
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
        $policy = $context['rate_limit_policy'] ?? [];

        return is_array($policy) ? $policy : [];
    }

    /**
     * Execute the job.
     */
    public function handle(InvestmentService $investmentService): void
    {
        $investmentService->markPriceFetchAttempted($this->investment);

        try {
            // Fetch and save investment prices from provider
            $investmentService->fetchAndSavePrices($this->investment);
            $investmentService->markPriceFetchSucceeded($this->investment);

            // Use the InvestmentService to recalculate the related accounts
            // TODO: this should be done once for all accounts
            $investmentService->recalculateRelatedAccounts($this->investment);
        } catch (Throwable $throwable) {
            $investmentService->markPriceFetchFailed($this->investment, $throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
