<?php

namespace Tests\Browser\Pages\Accounts;

use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

/**
 * Verifies that calendar dates from the API / server-rendered JS payload are
 * parsed as local-timezone dates in the browser, not as UTC midnight.
 *
 * Background: new Date("YYYY-MM-DD") treats the string as UTC midnight per the
 * JS spec. In timezones west of UTC this shifts the displayed date back by one
 * day. The fix uses a parseIsoDate() helper that constructs dates from year/
 * month/day components, anchored to the local timezone.
 *
 * These tests verify the Date objects in the DataTable have correct local
 * date components, independent of the test runner's timezone offset.
 */
#[Group('critical')]
class ScheduleDateDisplayTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_next_date_in_schedule_table_has_correct_local_date_components(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);
        $account = $user->accounts()->where('name', 'Cash account EUR')->first();
        $payee = $user->payees()->first();

        // Use a fixed far-future date to avoid any collision with seeded data.
        $nextDateString = '2035-07-15';

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->withdrawal_schedule($user)
            ->create(['user_id' => $user->id]);

        TransactionSchedule::where('transaction_id', $transaction->id)->update([
            'next_date' => $nextDateString,
            'automatic_recording' => false,
        ]);

        // Set a concrete account on the schedule detail so the row appears on
        // the account page we are about to load.
        $transaction->config->update([
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $account, $transaction, $nextDateString) {
            $browser
                ->loginAs($user)
                ->visitRoute('account-entity.show', ['account_entity' => $account->id])
                ->waitFor('#scheduleTable')
                ->waitUsing(10, 75, fn () => $this->getTableRowCount($browser, '#scheduleTable') >= 1);

            // Extract the Date object that DataTables has stored for this row's
            // next_date field, then read its LOCAL date components.
            [$year, $month, $day] = $browser->script("
                const table = $('#scheduleTable').DataTable();
                const rows = table.rows().data().toArray();
                const row = rows.find(r => r.id === {$transaction->id});
                if (!row || !row.transaction_schedule || !row.transaction_schedule.next_date) {
                    return [null, null, null];
                }
                const d = row.transaction_schedule.next_date;
                return [d.getFullYear(), d.getMonth() + 1, d.getDate()];
            ")[0];

            [$expectYear, $expectMonth, $expectDay] = array_map('intval', explode('-', $nextDateString));

            $this->assertSame(
                $expectYear,
                $year,
                "next_date year should be {$expectYear} in local time (got {$year}); " .
                'new Date("YYYY-MM-DD") UTC parsing would give the wrong local year near midnight.'
            );

            $this->assertSame(
                $expectMonth,
                $month,
                "next_date month should be {$expectMonth} in local time (got {$month})."
            );

            $this->assertSame(
                $expectDay,
                $day,
                "next_date day should be {$expectDay} in local time (got {$day}); " .
                'this is the reported off-by-one bug: date shifts to the previous day.'
            );
        });
    }

    public function test_start_date_in_schedule_table_has_correct_local_date_components(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);
        $account = $user->accounts()->where('name', 'Cash account EUR')->first();
        $payee = $user->payees()->first();

        $startDateString = '2035-03-01';
        $nextDateString = '2035-03-01';

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->withdrawal_schedule($user)
            ->create(['user_id' => $user->id]);

        TransactionSchedule::where('transaction_id', $transaction->id)->update([
            'start_date' => $startDateString,
            'next_date' => $nextDateString,
            'automatic_recording' => false,
        ]);

        $transaction->config->update([
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $account, $transaction, $startDateString) {
            $browser
                ->loginAs($user)
                ->visitRoute('account-entity.show', ['account_entity' => $account->id])
                ->waitFor('#scheduleTable')
                ->waitUsing(10, 75, fn () => $this->getTableRowCount($browser, '#scheduleTable') >= 1);

            [$year, $month, $day] = $browser->script("
                const table = $('#scheduleTable').DataTable();
                const rows = table.rows().data().toArray();
                const row = rows.find(r => r.id === {$transaction->id});
                if (!row || !row.transaction_schedule || !row.transaction_schedule.start_date) {
                    return [null, null, null];
                }
                const d = row.transaction_schedule.start_date;
                return [d.getFullYear(), d.getMonth() + 1, d.getDate()];
            ")[0];

            [$expectYear, $expectMonth, $expectDay] = array_map('intval', explode('-', $startDateString));

            $this->assertSame($expectYear, $year);
            $this->assertSame($expectMonth, $month);
            $this->assertSame(
                $expectDay,
                $day,
                "start_date day should be {$expectDay} in local time (got {$day}); " .
                'UTC midnight parsing would shift this to the last day of the previous month.'
            );
        });
    }
}
