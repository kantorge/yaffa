<?php

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\AccountEntity;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\InvestmentService;
use Illuminate\Database\Migrations\Migration;

/**
 * `Transaction::scheduleInstances()` now compounds each instance's amount by a per-schedule
 * inflation multiplier (`InflationCalculator`, introduced alongside the Budget entity in this
 * same redesign - see .ai/docs/features/budget-schedule-redesign/architecture.md), and
 * `CalculateAccountMonthlySummary::getAccountBalanceForecastData()`/`getInvestmentValueForecastData()`
 * both apply it. Pre-4.0, `transaction_schedules.inflation` was a real, user-settable column that
 * nothing ever read - so any pre-existing schedule with a non-zero rate has had it sitting inert
 * until now. Nothing transforms `transaction_schedules` in this release (unlike the budget-only
 * transaction conversion), so there is no data change to hook this into - it's a pure code
 * behavior change, but the effect is the same: cached forecast buckets keep serving pre-inflation
 * numbers until something recalculates them.
 *
 * Recalculates the `account_balance-forecast` and `investment_value-forecast` buckets for every
 * account of every user, synchronously (handle() called directly, not queued), so this is
 * guaranteed complete before `php artisan migrate` returns rather than left to the nightly cron
 * or a per-schedule edit to eventually catch up. Deliberately not scoped to only schedules with a
 * non-zero inflation rate - self-hosted instances are expected to have a small enough dataset that
 * the extra overhead of recalculating every account isn't worth the added complexity of filtering.
 */
return new class () extends Migration {
    public function up(): void
    {
        $investmentService = app(InvestmentService::class);
        $budgetService = app(BudgetService::class);

        User::all()->each(function (User $user) use ($investmentService, $budgetService): void {
            $user->load('accounts');

            $user->accounts->each(function (AccountEntity $account) use ($user, $investmentService, $budgetService): void {
                (new CalculateAccountMonthlySummary($user, 'account_balance-forecast', $account))
                    ->handle($investmentService, $budgetService);

                (new CalculateAccountMonthlySummary($user, 'investment_value-forecast', $account))
                    ->handle($investmentService, $budgetService);
            });
        });
    }

    public function down(): void
    {
        // Recalculating cached forecast data has no meaningful reverse.
    }
};
