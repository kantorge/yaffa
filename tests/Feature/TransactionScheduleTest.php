<?php

namespace Tests\Feature;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transaction_schedule_has_the_active_flag_correctly_set(): void
    {
        $transaction = $this->createScheduledTransaction([
            'start_date' => now()->subMonth(),
            'next_date' => now()->subMonth(),
            'end_date' => null,
            'frequency' => 'DAILY',
            'count' => null,
            'interval' => 1,
        ]);

        // Assert that the active flag is set to true
        $this->assertTrue($transaction->transactionSchedule->active);

        // Update the transaction schedule to be inactive
        $transaction->transactionSchedule->update([
            'next_date' => null,
            'end_date' => now()->subDay(),
        ]);

        $transaction->transactionSchedule->refresh();

        // Assert that the active flag is set to false
        $this->assertFalse($transaction->transactionSchedule->active);
    }

    public function testCatchUpToDateAdvancesNextDateToTodayOrLater(): void
    {
        $transaction = $this->createScheduledTransaction([
            'start_date' => Carbon::now()->subDays(30),
            'next_date' => Carbon::now()->subDays(30),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($transaction->transactionSchedule->catchUpToDate());
        $this->assertTrue($transaction->transactionSchedule->next_date->gte(Carbon::today()));
    }

    public function testCatchUpToDateAdvancesOldDailyScheduleBeyondRecurrVirtualLimit(): void
    {
        // start_date is ~3 years back, so the gap to today exceeds Recurr's default
        // virtualLimit of 732 daily occurrences - regression test for that ceiling.
        $transaction = $this->createScheduledTransaction([
            'start_date' => Carbon::now()->subYears(3),
            'next_date' => Carbon::now()->subYears(3),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($transaction->transactionSchedule->catchUpToDate());
        $this->assertTrue($transaction->transactionSchedule->next_date->gte(Carbon::today()));
    }

    public function testCatchUpToDateLeavesNextDateNullWhenScheduleExhaustedBeforeTarget(): void
    {
        $transaction = $this->createScheduledTransaction([
            'start_date' => Carbon::now()->subDays(30),
            'next_date' => Carbon::now()->subDays(30),
            'end_date' => Carbon::now()->subDays(10),
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($transaction->transactionSchedule->catchUpToDate());
        $this->assertNull($transaction->transactionSchedule->next_date);

        $transaction->transactionSchedule->refresh();
        $this->assertFalse($transaction->transactionSchedule->active);
    }

    public function testCatchUpToDateIsNoOpWhenNextDateAlreadyAtOrAfterTarget(): void
    {
        $futureNextDate = Carbon::now()->addDays(5)->startOfDay();

        $transaction = $this->createScheduledTransaction([
            'start_date' => Carbon::now()->subDays(30),
            'next_date' => $futureNextDate,
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($transaction->transactionSchedule->catchUpToDate());
        $this->assertTrue($transaction->transactionSchedule->next_date->eq($futureNextDate));
    }

    public function testCatchUpToDateAcceptsExplicitTargetDate(): void
    {
        $target = \Illuminate\Support\Carbon::now()->addMonths(6)->startOfDay();

        $transaction = $this->createScheduledTransaction([
            'start_date' => Carbon::now()->subDays(30),
            'next_date' => Carbon::now()->subDays(30),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($transaction->transactionSchedule->catchUpToDate($target));
        $this->assertTrue($transaction->transactionSchedule->next_date->gte($target));
    }

    public function testCatchUpToDateAdvancesMonthlyWeekdayScheduleAcrossSeveralMonths(): void
    {
        $transaction = $this->createScheduledTransaction([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '1WE',
        ]);

        $target = \Illuminate\Support\Carbon::parse('2026-04-01');

        $this->assertTrue($transaction->transactionSchedule->catchUpToDate($target));
        $this->assertSame('2026-04-01', $transaction->transactionSchedule->next_date->format('Y-m-d'));
    }

    /**
     * Create a user-owned, persisted, scheduled withdrawal transaction with the given
     * schedule attributes - mirrors the fixture used by
     * test_a_transaction_schedule_has_the_active_flag_correctly_set, factored out so
     * catchUpToDate() tests (which must persist via save()) can reuse it.
     *
     * @param  array{start_date: Carbon, next_date: Carbon, end_date: Carbon|null, frequency: string, interval: int, count: int|null, by_day?: string|null}  $scheduleAttributes
     */
    private function createScheduledTransaction(array $scheduleAttributes): Transaction
    {
        /** @var User $user */
        $user = User::factory()->create();

        AccountGroup::factory()
            ->for($user)
            ->create();

        Currency::factory()
            ->for($user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);

        AccountEntity::factory()
            ->for($user)
            ->for(
                Account::factory()
                    ->withUser($user)
                    ->create(),
                'config'
            )
            ->create();

        AccountEntity::factory()->asPayee($user)->create();

        /** @var Transaction $transaction */
        $transaction = Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'account_from_id' => $user->accounts()->first()->id,
                    'account_to_id' => $user->payees()->first()->id,
                    'amount_from' => 100,
                    'amount_to' => 100,
                ]),
                'config'
            )
            ->create([
                'date' => null,
                'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            ]);

        $transaction->schedule = true;
        $transaction->save();

        $transaction->transactionSchedule()->create(array_merge([
            'inflation' => null,
            'automatic_recording' => true,
        ], $scheduleAttributes));

        return $transaction->fresh(['transactionSchedule']);
    }
}
