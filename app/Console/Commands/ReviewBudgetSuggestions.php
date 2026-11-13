<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BudgetSuggestionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Signature('budget-suggestions:review {user : User ID} {--through= : Data-through month (Y-m)} {--sensitivity= : low|medium|high} {--bias= : neutral|conservative} {--json : Print the service result as JSON} {--backtest : Re-run for each of the last 24 months and count suggestions per category per year}')]
#[Description('Review all budgets of a user against recent actuals (the same result as the budget-suggestions API endpoint)')]
class ReviewBudgetSuggestions extends Command
{
    private const BACKTEST_MONTHS = 24;

    private const BACKTEST_MAX_PER_YEAR = 2;

    public function handle(BudgetSuggestionService $service): int
    {
        $user = User::query()->find((int) $this->argument('user'));
        if ($user === null) {
            $this->error('Invalid user');

            return Command::FAILURE;
        }

        $through = $this->option('through');
        $sensitivity = $this->option('sensitivity');
        $bias = $this->option('bias');

        if ($through !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $through)) {
            $this->error('--through must be a month in Y-m format');

            return Command::FAILURE;
        }
        if ($sensitivity !== null && ! array_key_exists($sensitivity, config('yaffa.budget_suggestions.sensitivity'))) {
            $this->error('--sensitivity must be one of: ' . implode(', ', array_keys(config('yaffa.budget_suggestions.sensitivity'))));

            return Command::FAILURE;
        }
        if ($bias !== null && ! in_array($bias, ['neutral', 'conservative'], true)) {
            $this->error('--bias must be one of: neutral, conservative');

            return Command::FAILURE;
        }

        if ($this->option('backtest') && $through !== null) {
            $this->error('--through cannot be combined with --backtest (it runs the last 24 complete months)');

            return Command::FAILURE;
        }

        try {
            return $this->option('backtest')
                ? $this->backtest($service, $user, $sensitivity, $bias)
                : $this->review($service, $user, $through, $sensitivity, $bias);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function review(BudgetSuggestionService $service, User $user, ?string $through, ?string $sensitivity, ?string $bias): int
    {
        $started = microtime(true);
        $result = $service->run($user, null, $through, $sensitivity, $bias);
        $elapsed = microtime(true) - $started;

        if ($this->option('json')) {
            // Unchanged service result, so it matches the endpoint body (timing is not added).
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $this->table(
            ['Category', 'Verdict', 'Type', 'Current', 'Suggested', 'Monthly impact', '% of monthly expenses', 'Effective', 'Persistence'],
            collect($result['units'])
                ->map(fn ($u) => [
                    $u['category_name'],
                    $u['verdict'],
                    $u['suggestion']['proposal_type'] ?? '',
                    $u['suggestion']['current_amount'] ?? '',
                    $u['suggestion']['suggested_amount'] ?? '',
                    $u['suggestion']['monthly_impact'] ?? '',
                    isset($u['suggestion']['impact_share']) ? round($u['suggestion']['impact_share'] * 100, 2) . '%' : '',
                    $u['suggestion']['effective_date'] ?? '',
                    isset($u['suggestion']) ? "{$u['suggestion']['persistence']['k']} of {$u['suggestion']['persistence']['n']}" : '',
                ])->all()
        );

        $counts = collect($result['units'])->countBy('verdict')->map(fn ($n, $v) => "{$v}: {$n}")->implode(', ');
        $this->info("Data through {$result['run']['data_through']}, sensitivity {$result['run']['sensitivity']}, bias {$result['run']['bias']}.");
        $this->info($counts);
        $this->info(sprintf('Elapsed: %.2f s', $elapsed));

        return Command::SUCCESS;
    }

    private function backtest(BudgetSuggestionService $service, User $user, ?string $sensitivity, ?string $bias): int
    {
        $lastComplete = Carbon::now()->startOfMonth()->subMonth();
        $names = [];
        $counts = []; // category id => year => suggestions

        for ($i = self::BACKTEST_MONTHS - 1; $i >= 0; $i--) {
            $month = $lastComplete->copy()->subMonths($i);
            $result = $service->run($user, null, $month->format('Y-m'), $sensitivity, $bias);

            foreach ($result['units'] as $unit) {
                $names[$unit['category_id']] = $unit['category_name'];
                if ($unit['verdict'] === 'suggestion') {
                    $counts[$unit['category_id']][$month->year] = ($counts[$unit['category_id']][$month->year] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        foreach ($counts as $categoryId => $perYear) {
            ksort($perYear);
            $rows[] = [
                'category_id' => $categoryId,
                'category_name' => $names[$categoryId],
                'suggestions_per_year' => $perYear,
                'flagged' => max($perYear) > self::BACKTEST_MAX_PER_YEAR,
            ];
        }
        usort($rows, fn ($a, $b) => max($b['suggestions_per_year']) <=> max($a['suggestions_per_year']));

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $this->table(
            ['Category', 'Suggestions per year', 'Flagged'],
            array_map(fn ($r) => [
                $r['category_name'],
                collect($r['suggestions_per_year'])->map(fn ($n, $y) => "{$y}: {$n}")->implode(', '),
                $r['flagged'] ? 'yes' : '',
            ], $rows)
        );
        $this->info(sprintf(
            '%d categories got a suggestion in the last %d months; %d flagged (more than %d in a year).',
            count($rows),
            self::BACKTEST_MONTHS,
            count(array_filter($rows, fn ($r) => $r['flagged'])),
            self::BACKTEST_MAX_PER_YEAR
        ));

        return Command::SUCCESS;
    }
}
