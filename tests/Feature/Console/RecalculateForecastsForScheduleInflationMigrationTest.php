<?php

namespace Tests\Feature\Console;

use App\Models\AccountEntity;
use App\Models\AccountMonthlySummary;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for 2026_08_30_000001_recalculate_forecasts_for_schedule_inflation.php: a pre-existing
 * schedule's `inflation` rate (a column that existed but was never read by any calculation before
 * this same v4 redesign) must be reflected in the cached `account_balance-forecast` bucket once
 * this migration runs, not left stale until the nightly cron or a schedule edit happens to
 * recalculate it.
 */
class RecalculateForecastsForScheduleInflationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recalculates_the_forecast_bucket_for_a_pre_existing_schedule_with_inflation(): void
    {
        Carbon::useMonthsOverflow(false);

        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => now()->addMonths(14)->endOfMonth(),
        ]);

        $account = AccountEntity::factory()->asAccount($user)->create();
        AccountEntity::factory()->asPayee($user)->create();

        $scheduleStart = now()->startOfMonth()->subMonths(2);

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal_schedule($user)
            ->create();

        $transaction->config()->update([
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        $transaction->transactionSchedule->update([
            'start_date' => $scheduleStart,
            'next_date' => $scheduleStart,
            'end_date' => now()->addMonths(11)->endOfMonth(),
            'count' => null,
            'interval' => 1,
            'frequency' => 'MONTHLY',
            'inflation' => 10.0,
        ]);

        // This schedule didn't exist when the suite's one-time migration run populated
        // account_monthly_summaries (the users table was empty then), so nothing is cached for
        // it yet - simulating a pre-existing v3.x schedule whose inflation rate had never been
        // applied to anything before upgrading.
        $this->assertDatabaseMissing('account_monthly_summaries', [
            'account_entity_id' => $account->id,
            'data_type' => 'forecast',
        ]);

        // Re-run just this migration. Safe under RefreshDatabase's transaction wrapping: its
        // down() is a no-op (no DDL), so this is a plain row delete/reinsert in the `migrations`
        // table, not the implicit-commit DDL that would break transactional test isolation.
        DB::table('migrations')
            ->where('migration', '2026_08_30_000001_recalculate_forecasts_for_schedule_inflation')
            ->delete();
        $this->assertSame(0, $this->artisan('migrate', ['--force' => true])->run());

        $summaryRecords = AccountMonthlySummary::where([
            'account_entity_id' => $account->id,
            'transaction_type' => 'account_balance',
            'data_type' => 'forecast',
        ])->orderBy('date')->get();

        // The 14-month window always crosses exactly one January 1st, so every record's year is
        // either the schedule's start year (no compounding yet) or exactly one year later
        // (compounded once).
        $this->assertGreaterThan(0, $summaryRecords->count());
        $summaryRecords->each(function (AccountMonthlySummary $summaryRecord) use ($scheduleStart) {
            $expectedMultiplier = $summaryRecord->date->year > $scheduleStart->year ? 1.1 : 1.0;
            $this->assertEqualsWithDelta(-100 * $expectedMultiplier, $summaryRecord->amount->getAmount()->toFloat(), 0.001);
        });

        Carbon::resetMonthsOverflow();
    }
}
