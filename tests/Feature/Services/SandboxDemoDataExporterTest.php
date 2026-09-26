<?php

namespace Tests\Feature\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSchedule;
use App\Models\User;
use App\Services\SandboxDemoDataExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxDemoDataExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_dates_moves_start_date_and_rrule_until_together(): void
    {
        $user = User::factory()->create(['id' => 1]);

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::factory()->create(['user_id' => $user->id])->id,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => '2007-06-01',
            'end_date' => '2018-04-30',
        ]);
        $noEnd = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $budget->category_id,
            'frequency' => 'MONTHLY',
            'start_date' => '2007-06-01',
            'end_date' => null,
        ]);

        $transaction = Transaction::factory()->for($user)->withdrawal_schedule($user)->create();
        $transaction->transactionSchedule->update([
            'start_date' => '2007-07-21',
            'next_date' => '2008-11-21',
            'frequency' => 'MONTHLY',
            'end_date' => '2008-01-31',
            'count' => null,
        ]);

        app(SandboxDemoDataExporter::class)->shiftDates(12);

        $fresh = Budget::findOrFail($budget->id);
        $this->assertSame('2008-06-01', $fresh->start_date->toDateString());
        $this->assertSame('2019-04-30', $fresh->end_date->toDateString());
        $this->assertNull(Budget::findOrFail($noEnd->id)->end_date);

        $schedule = TransactionSchedule::findOrFail($transaction->transactionSchedule->id);
        $this->assertSame('2008-07-21', $schedule->start_date->toDateString());
        // Jan 31 + 12 months; time suffix of UNTIL is preserved
        $this->assertSame('2009-01-31', $schedule->end_date->toDateString());

        // ...and shifts back to the original (UNTIL on the last day of a 31-day month round-trips)
        app(SandboxDemoDataExporter::class)->shiftDates(-12);
        $this->assertSame('2018-04-30', Budget::findOrFail($budget->id)->end_date->toDateString());
    }
}
