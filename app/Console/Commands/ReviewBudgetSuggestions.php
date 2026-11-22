<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BudgetSuggestionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('budget-suggestions:review {user : User ID} {--through= : Data-through month (Y-m)} {--sensitivity= : low|medium|high} {--bias= : neutral|conservative|conservative_rounded} {--json : Print the service result as JSON}')]
#[Description('Review all budgets of a user against recent actuals (the same result as the budget-suggestions API endpoint)')]
class ReviewBudgetSuggestions extends Command
{
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
        if ($bias !== null && ! in_array($bias, ['neutral', 'conservative', 'conservative_rounded'], true)) {
            $this->error('--bias must be one of: neutral, conservative, conservative_rounded');

            return Command::FAILURE;
        }

        try {
            return $this->review($service, $user, $through, $sensitivity, $bias);
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
}
