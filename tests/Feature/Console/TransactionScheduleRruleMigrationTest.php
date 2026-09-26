<?php

namespace Tests\Feature\Console;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Integration coverage for the 3-migration sequence that collapses transaction_schedules'
 * frequency/interval/count/end_date/by_day/by_month columns into `rrule`
 * (2026_08_04_000001_add_rrule_to_transaction_schedules_table,
 * 2026_08_04_000002_backfill_rrule_on_transaction_schedules_table,
 * 2026_08_04_000003_drop_recurrence_columns_from_transaction_schedules_table) - see
 * recurrence-rrule-storage.md Section 7. Unlike the by_day/by_month columns these replace,
 * frequency/interval/count/end_date are real pre-4.0 data, so this suite is a golden-output check
 * (the exact RRULE string, not just "a string was produced"), mirroring
 * tests/Feature/Console/BudgetMigrationTest.php's approach for the sibling budget conversion.
 *
 * Uses DatabaseMigrations (full migrate:fresh once per test method), not RefreshDatabase - DDL
 * statements cause an implicit commit in MySQL, which would break RefreshDatabase's
 * transaction-per-test isolation.
 */
class TransactionScheduleRruleMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetToPreMigrationBaseline();

        $this->user = User::factory()->create();
    }

    /**
     * Rolls back to the schema state immediately before this migration sequence - the real
     * pre-4.0 shape (frequency/interval/count/end_date as real columns, no rrule/by_day/by_month
     * at all), matching what every genuine 3.x-upgrading installation actually has.
     */
    private function resetToPreMigrationBaseline(): void
    {
        $migrationsToRollBack = DB::table('migrations')
            ->where('migration', '>=', '2026_08_04_000001_add_rrule_to_transaction_schedules_table')
            ->count();

        if ($migrationsToRollBack > 0) {
            Artisan::call('migrate:rollback', ['--step' => $migrationsToRollBack]);
        }
    }

    private function requireMigration(string $filename): object
    {
        return require database_path("migrations/{$filename}.php");
    }

    private function insertLegacySchedule(array $overrides = []): int
    {
        $transaction = Transaction::factory()->withdrawal($this->user)->create(['user_id' => $this->user->id]);

        return DB::table('transaction_schedules')->insertGetId(array_merge([
            'transaction_id' => $transaction->id,
            'automatic_recording' => false,
            'active' => true,
            'start_date' => '2024-01-01',
            'next_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => null,
            'by_month' => null,
            'count' => null,
            'end_date' => null,
        ], $overrides));
    }

    public function test_backfill_produces_the_expected_rrule_for_each_legacy_shape(): void
    {
        $plain = $this->insertLegacySchedule(['frequency' => 'DAILY', 'interval' => 1]);
        $withInterval = $this->insertLegacySchedule(['frequency' => 'WEEKLY', 'interval' => 2]);
        $withCount = $this->insertLegacySchedule(['frequency' => 'MONTHLY', 'count' => 5]);
        $withEndDate = $this->insertLegacySchedule(['frequency' => 'YEARLY', 'end_date' => '2025-12-31']);
        $withOrdinalWeekday = $this->insertLegacySchedule(['frequency' => 'MONTHLY', 'by_day' => '1WE']);
        $withOrdinalWeekdayAndMonth = $this->insertLegacySchedule([
            'frequency' => 'YEARLY',
            'by_day' => '-1FR',
            'by_month' => 11,
        ]);

        $this->assertSame(0, $this->artisan('migrate', ['--force' => true])->run());

        $this->assertFalse(Schema::hasColumn('transaction_schedules', 'frequency'));
        $this->assertFalse(Schema::hasColumn('transaction_schedules', 'by_day'));
        $this->assertFalse(Schema::hasColumn('transaction_schedules', 'by_month'));
        $this->assertFalse(Schema::hasColumn('transaction_schedules', 'count'));
        $this->assertFalse(Schema::hasColumn('transaction_schedules', 'end_date'));

        $rrule = fn (int $id) => DB::table('transaction_schedules')->where('id', $id)->value('rrule');

        $this->assertSame('FREQ=DAILY;INTERVAL=1', $rrule($plain));
        $this->assertSame('FREQ=WEEKLY;INTERVAL=2', $rrule($withInterval));
        $this->assertSame('FREQ=MONTHLY;COUNT=5;INTERVAL=1', $rrule($withCount));
        $this->assertSame('FREQ=YEARLY;UNTIL=20251231T000000;INTERVAL=1', $rrule($withEndDate));
        $this->assertSame('FREQ=MONTHLY;INTERVAL=1;BYDAY=1WE', $rrule($withOrdinalWeekday));
        $this->assertSame('FREQ=YEARLY;INTERVAL=1;BYDAY=-1FR;BYMONTH=11', $rrule($withOrdinalWeekdayAndMonth));
    }

    public function test_drop_migration_refuses_to_run_if_a_row_has_no_rrule(): void
    {
        $this->insertLegacySchedule();

        $this->requireMigration('2026_08_04_000001_add_rrule_to_transaction_schedules_table')->up();
        $this->requireMigration('2026_08_04_000002_backfill_rrule_on_transaction_schedules_table')->up();

        DB::table('transaction_schedules')->update(['rrule' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('still have no `rrule` value');

        $this->requireMigration('2026_08_04_000003_drop_recurrence_columns_from_transaction_schedules_table')->up();
    }
}
