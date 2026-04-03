<?php

namespace App\Console\Commands;

use App\Jobs\GetInvestmentPrices as GetInvestmentPricesJob;
use App\Models\Investment;
use App\Services\InvestmentProviderPreflightService;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Console\Command;

class GetInvestmentPrices extends Command
{
    public function __construct(
        private InvestmentProviderPreflightService $preflightService,
        private InvestmentService $investmentService
    ) {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:investment-prices:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run retrieval of investment prices for all investments with known price providers.';

    /**
     * Execute the console command.
     *
     * The command runs in several stages:
     *
     * 1. **Collection**: Retrieves all investments with auto-update enabled and a configured price
     *    provider, including inactive ones. Inactive investments are still processed so their price
     *    history stays up to date, but they are dispatched last within their rate-limit bucket.
     *
     * 2. **Preflight**: Each investment is validated by {@see InvestmentProviderPreflightService}.
     *    Investments that fail preflight are immediately marked as failed and excluded from
     *    dispatching. Investments that pass are added to the eligible collection together with
     *    their resolved provider context (credentials, settings, rate-limit policy, etc.).
     *
     * 3. **Grouping**: Eligible investments are grouped by their provider's rate-limit bucket key
     *    (e.g. `alpha_vantage`). All investments sharing a bucket are subject to the same daily
     *    call limit, so they must be budgeted together.
     *
     * 4. **Budgeting**: For each bucket group, {@see resolveDispatchBudget()} calculates how many
     *    jobs can still be dispatched today, given the configured daily limit, a reserve margin,
     *    and the number of fetches already attempted today for that user/provider combination.
     *
     * 5. **Sorting and dispatch**: Within each bucket group, investments are sorted so that active
     *    investments are dispatched before inactive ones. Within each activity tier, the investment
     *    with the oldest last-fetch attempt is dispatched first, ensuring the available quota is
     *    distributed fairly across all investments in the group.
     */
    public function handle(): int
    {
        $scanned = 0;
        $failedPreflight = 0;
        $dispatched = 0;
        $skippedBudget = 0;

        $investments = Investment::where('auto_update', true)
            ->whereNotNull('investment_price_provider')
            ->with(['user'])
            ->get();

        /** @var Collection<int, array{investment: Investment, context: array<string, mixed>}> $eligible */
        $eligible = collect();

        $investments->each(function (Investment $investment) use ($eligible, &$scanned, &$failedPreflight): void {
            $scanned++;
            $result = $this->preflightService->validate($investment);

            if (! $result['ok']) {
                $failedPreflight++;
                $this->investmentService->markPriceFetchFailed($investment, (string) ($result['reason'] ?? __('Preflight validation failed.')));

                if ($this->output->isVerbose()) {
                    $activeLabel = $investment->active ? 'active' : 'inactive';
                    $reason = (string) ($result['reason'] ?? 'Preflight validation failed.');
                    $this->line("  <comment>SKIP</comment> [{$investment->id}] {$investment->name} ({$activeLabel}): {$reason}");
                }

                return;
            }

            $eligible->push([
                'investment' => $investment,
                'context' => $result['context'] ?? [],
            ]);
        });

        /** @var array<string, int> $attemptedTodayCounts */
        $attemptedTodayCounts = Investment::query()
            ->whereDate('last_price_fetch_attempted_at', Carbon::today())
            ->selectRaw('user_id, investment_price_provider, COUNT(*) as count')
            ->groupBy('user_id', 'investment_price_provider')
            ->get()
            ->mapWithKeys(fn (Investment $row) => [
                "{$row->user_id}:{$row->investment_price_provider}" => (int) ($row->getAttribute('count') ?? 0),
            ])
            ->all();

        $eligible
            ->groupBy(function (array $item): string {
                $bucketKey = $item['context']['rate_limit_policy']['bucketKey'] ?? null;

                return is_string($bucketKey) ? $bucketKey : 'unknown';
            })
            ->each(function (Collection $group) use (&$dispatched, &$skippedBudget, $attemptedTodayCounts): void {
                $first = $group->first();
                if (! is_array($first)) {
                    return;
                }

                /** @var array<string, int|string|null> $policy */
                $policy = is_array($first['context']['rate_limit_policy'] ?? null)
                    ? $first['context']['rate_limit_policy']
                    : [];

                $bucketKey = (string) ($policy['bucketKey'] ?? 'unknown');

                $dispatchable = $group
                    ->sortBy(function (array $item) {
                        /** @var Investment $investment */
                        $investment = $item['investment'];

                        // Active investments sort before inactive; within each tier, oldest first
                        return [
                            $investment->active ? 0 : 1,
                            $investment->last_price_fetch_attempted_at?->getTimestamp() ?? 0,
                        ];
                    })
                    ->values();

                $budget = $this->resolveDispatchBudget($dispatchable, $policy, $attemptedTodayCounts);

                if ($this->output->isVeryVerbose()) {
                    $this->line("  <comment>BUCKET</comment> {$bucketKey}: {$dispatchable->count()} eligible, budget={$budget}");
                }

                if ($budget <= 0) {
                    $skippedBudget += $dispatchable->count();

                    return;
                }

                $skippedBudget += max(0, $dispatchable->count() - $budget);

                $dispatchable->take($budget)->each(function (array $item) use (&$dispatched): void {
                    /** @var Investment $investment */
                    $investment = $item['investment'];
                    /** @var array<string, int|string|null> $rateLimitPolicy */
                    $rateLimitPolicy = is_array($item['context']['rate_limit_policy'] ?? null)
                        ? $item['context']['rate_limit_policy']
                        : [];

                    GetInvestmentPricesJob::dispatch($investment, $rateLimitPolicy);
                    $dispatched++;

                    if ($this->output->isVerbose()) {
                        $activeLabel = $investment->active ? 'active' : 'inactive';
                        $this->line("  <info>DISPATCH</info> [{$investment->id}] {$investment->name} ({$activeLabel})");
                    }
                });
            });

        if ($this->output->isVerbose()) {
            $this->info("Scanned: {$scanned} | Failed preflight: {$failedPreflight} | Skipped (budget): {$skippedBudget} | Dispatched: {$dispatched}");
        }

        return 0;
    }

    /**
     * @param  Collection<int, array{investment: Investment, context: array<string, mixed>}>  $dispatchable
     * @param  array<string, int|string|null>  $policy
     * @param  array<string, int>  $attemptedTodayCounts
     */
    private function resolveDispatchBudget(Collection $dispatchable, array $policy, array $attemptedTodayCounts): int
    {
        $groupCount = $dispatchable->count();
        if ($groupCount === 0) {
            return 0;
        }

        $perDay = isset($policy['perDay']) && is_numeric($policy['perDay'])
            ? (int) $policy['perDay']
            : null;
        if ($perDay === null) {
            return $groupCount;
        }

        /** @var Investment|null $firstInvestment */
        $firstInvestment = $dispatchable->first()['investment'] ?? null;
        if (! $firstInvestment instanceof Investment) {
            return 0;
        }

        $reserve = isset($policy['reserve']) && is_numeric($policy['reserve'])
            ? max(0, (int) $policy['reserve'])
            : 0;

        $attemptedToday = $attemptedTodayCounts["{$firstInvestment->user_id}:{$firstInvestment->investment_price_provider}"] ?? 0;

        return max(0, min($groupCount, $perDay - $reserve - $attemptedToday));
    }
}
