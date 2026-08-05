<?php

namespace Tests\Unit\Models;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transaction_schedule_has_the_active_flag_correctly_set(): void
    {
        // Create a user which will own the assets
        /** @var User $user */
        $user = User::factory()->create();

        // Create: account group, currency, account, payee
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

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

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

        // Intentionally set the schedule flag later, to avoid the creating closure
        $transaction->schedule = true;
        $transaction->save();

        // Create an active transaction schedule for this transaction
        $transaction->transactionSchedule()->create([
            'start_date' => now()->subMonth(),
            'next_date' => now()->subMonth(),
            'end_date' => null,
            'frequency' => 'DAILY',
            'count' => null,
            'interval' => 1,
            'inflation' => null,
            'automatic_recording' => true,
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

    public function testIsActiveReturnsTrueWhenNextDateIsSet(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($schedule->isActive());
    }

    public function testIsActiveReturnsFalseWhenNextDateIsNotSetAndNoFutureRecurrences(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::now()->subDays(10),
            'next_date' => null,
            'end_date' => Carbon::now()->subDay(),
            'frequency' => 'DAILY',
            'count' => null,
            'interval' => 1,
        ]);

        $this->assertFalse($schedule->isActive());
    }

    public function testIsActiveReturnsTrueWhenNextDateIsNotSetButHasFutureRecurrences(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(10),
            'count' => null,
            'interval' => 1,
            'frequency' => 'DAILY',
        ]);

        $this->assertTrue($schedule->isActive());
    }

    public function testOccursOnReturnsFalseWhenDateDoesNotMatchOrdinalWeekdayRule(): void
    {
        // 2026-01-01 is a Thursday, not the first Wednesday of January 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => '1WE',
        ]);

        $this->assertFalse($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-01')));
    }

    public function testOccursOnReturnsTrueWhenDateMatchesOrdinalWeekdayRule(): void
    {
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => '1WE',
        ]);

        $this->assertTrue($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-07')));
    }

    public function testOccursOnReturnsFalseForANonMatchingWeekInsideTheRule(): void
    {
        // The second Wednesday of January 2026 is not "the first Wednesday".
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'by_day' => '1WE',
        ]);

        $this->assertFalse($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-14')));
    }

    public function testOccursOnHandlesIntervalForPlainFrequencyRules(): void
    {
        // Every 2 weeks from 2026-01-01: 01-01 and 01-15 occur, 01-08 does not.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'count' => null,
            'frequency' => 'WEEKLY',
            'interval' => 2,
        ]);

        $this->assertTrue($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-01')));
        $this->assertFalse($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-08')));
        $this->assertTrue($schedule->occursOn(\Illuminate\Support\Carbon::parse('2026-01-15')));
    }

    public function testIsActiveReturnsFalseWhenRecurrenceThrowsException(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDay(),
            'frequency' => 'INVALID_FREQUENCY',
        ]);

        $this->assertFalse($schedule->isActive());
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

    public function testGetNextInstanceComputesFirstWeekdayOfMonth(): void
    {
        // 2026-01-07 is the first Wednesday of January 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '1WE',
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-01-07', $next->format('Y-m-d'));
    }

    public function testGetNextInstanceComputesLastWeekdayOfMonth(): void
    {
        // 2026-01-30 is the last Friday of January 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '-1FR',
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-01-30', $next->format('Y-m-d'));
    }

    public function testGetNextInstanceComputesYearlyWeekdayScopedToMonth(): void
    {
        // 2026-11-27 is the last Friday of November 2026.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '-1FR',
            'by_month' => 11,
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-11-27', $next->format('Y-m-d'));
    }

    public function testGetNextInstanceResolvesYearlyWeekdayAcrossWholeYearWhenMonthOmitted(): void
    {
        // Documents recurr's actual semantics: a YEARLY ordinal BYDAY without BYMONTH
        // resolves against the whole year (last Friday of 2026 = 2026-12-25), not per
        // month. TransactionRequest validation rejects this combination at the API
        // boundary, but the model itself doesn't enforce it, so this pins the behavior
        // down as a safety net against a future regression silently changing it.
        $schedule = TransactionSchedule::factory()->make([
            'start_date' => Carbon::parse('2026-01-01'),
            'next_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '-1FR',
            'by_month' => null,
        ]);

        $next = $schedule->getNextInstance();

        $this->assertNotNull($next);
        $this->assertSame('2026-12-25', $next->format('Y-m-d'));
    }

    public function testIsActiveReturnsFalseWhenByDayFrequencyCombinationIsInvalid(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => null,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(10),
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'count' => null,
            'by_day' => '1WE',
        ]);

        $this->assertFalse($schedule->isActive());
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

    public function testCatchUpToDateReturnsFalseWhenRecurrenceThrowsException(): void
    {
        /** @var TransactionSchedule $schedule */
        $schedule = TransactionSchedule::factory()->make([
            'next_date' => Carbon::now()->subDays(10),
            'start_date' => Carbon::now()->subDays(30),
            'end_date' => Carbon::now()->addDay(),
            'frequency' => 'INVALID_FREQUENCY',
        ]);

        $this->assertFalse($schedule->catchUpToDate());
        $this->assertNotNull($schedule->next_date);
    }

    /**
     * Create a user-owned, persisted, scheduled withdrawal transaction with the given
     * schedule attributes - mirrors the fixture used by
     * test_a_transaction_schedule_has_the_active_flag_correctly_set, factored out so
     * catchUpToDate() tests (which must persist via save()) can reuse it.
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

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

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
