<?php

namespace App\Services;

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\AccountEntity;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class BudgetService
{
    public function __construct(private readonly RecurrenceRuleService $recurrenceRuleService)
    {
    }

    public function store(User $user, array $data): Budget
    {
        $budget = $user->budgets()->create($data);

        $this->recalculateAccountBalanceBudget($user, $budget->account_id);

        return $budget;
    }

    public function update(Budget $budget, array $data): Budget
    {
        $originalAccountId = $budget->account_id;

        $budget->fill($data);
        $budget->save();

        // The budget bucket a row feeds is keyed by its own account_id (FR-3); if that changed,
        // both the old account's (or the account-agnostic) bucket and the new one need recalculating.
        $this->recalculateAccountBalanceBudget($budget->user, $originalAccountId);
        if ($budget->account_id !== $originalAccountId) {
            $this->recalculateAccountBalanceBudget($budget->user, $budget->account_id);
        }

        return $budget;
    }

    /**
     * Replace a budget's recurrence going forward without rewriting its history: closes out the
     * source budget (its recurrence fields are overwritten with $data['original_schedule_config'],
     * which the frontend has already end_date-capped to the day before the new budget's
     * start_date) and creates $data as a brand new Budget row - mirroring how
     * TransactionApiController's 'replace' action closes a TransactionSchedule and creates a new
     * Transaction+TransactionSchedule, but simpler here since a Budget's recurrence fields live on
     * the row itself rather than a separate schedule model.
     */
    public function replace(User $user, array $data): Budget
    {
        return DB::transaction(function () use ($user, $data) {
            $sourceBudget = Budget::query()
                ->where('id', $data['id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            $originalAccountId = $sourceBudget->account_id;

            $sourceBudget->fill($data['original_schedule_config']);
            $sourceBudget->save();

            $newBudget = $this->store($user, $data);

            // store() above already recalculated the new budget's own account_id bucket, which
            // reflects the source budget's just-saved end_date too when both share an account -
            // only a *different* source account needs its own, separate recalculation.
            if ($originalAccountId !== $newBudget->account_id) {
                $this->recalculateAccountBalanceBudget($user, $originalAccountId);
            }

            return $newBudget;
        });
    }

    public function delete(Budget $budget): array
    {
        $success = false;
        $error = null;
        $user = $budget->user;
        $accountId = $budget->account_id;

        try {
            $success = (bool) $budget->delete();

            if ($success) {
                $this->recalculateAccountBalanceBudget($user, $accountId);
            } else {
                $error = __('Budget could not be deleted');
            }
        } catch (Throwable $e) {
            report($e);
            $error = __('Database error:') . ' ' . $e->getMessage();
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }

    /**
     * Recalculate the account-balance budget bucket (FR-3) a Budget row with the given
     * account_id feeds - the account-specific bucket when set, the account-agnostic bucket
     * (account_entity_id = null) otherwise. Queued rather than dispatch_sync, matching the
     * existing forecast-bucket convention (TransactionService::recalculateSummaryStandard), since
     * the horizon runs out to the user's end_date rather than a single month.
     */
    private function recalculateAccountBalanceBudget(User $user, ?int $accountId): void
    {
        $accountEntity = $accountId ? AccountEntity::find($accountId) : null;

        CalculateAccountMonthlySummary::dispatchNamed($user, 'account_balance-budget', $accountEntity);
    }

    /**
     * Project the calendar dates on which this budget's period recurs within [$from, $to]
     * (inclusive of both ends) - $from is often the budget's own start_date, which must itself
     * be included as the first occurrence.
     *
     * @return Carbon[]
     */
    public function projectOccurrences(Budget $budget, Carbon $from, Carbon $to): array
    {
        // updated_at can be null for rows inserted outside Eloquent (e.g. the demo seed's raw
        // INSERT, which omits timestamp columns) - the null-safe access mirrors
        // Transaction::scheduleInstances()'s identical schedule-occurrence cache key.
        $cacheKey = "budget-occurrences:{$budget->id}:{$budget->updated_at?->timestamp}:"
            . "{$from->toDateString()}:{$to->toDateString()}";

        $dateStrings = Cache::remember($cacheKey, now()->addHour(), function () use ($budget, $from, $to) {
            $recurrence = $this->recurrenceRuleService->getRecurrenceBetween(
                $budget->start_date,
                $budget->effectiveRrule(),
                $from,
                $to,
            );

            return collect($recurrence)
                ->map(fn ($occurrence) => Carbon::instance($occurrence->getStart())->toDateString())
                ->values()
                ->all();
        });

        return collect($dateStrings)->map(fn ($date) => Carbon::parse($date))->values()->all();
    }
}
