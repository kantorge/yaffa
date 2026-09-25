<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 year', 'now');
        $end = $this->faker->dateTimeBetween($start, $start->format('Y-m-d H:i:s') . ' +2 years');

        return [
            'start_date' => $start,
            'next_date' => $start,
            // count and end_date are mutually exclusive (backend enforces this via
            // `prohibits`); pass 'end_date' => null, 'count' => N for a COUNT-bounded schedule.
            'end_date' => $end,
            'frequency' => $this->faker->randomElement(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
            'interval' => $this->faker->numberBetween(1, 5),
            'automatic_recording' => false
        ];
    }

    /**
     * State for an RFC5545 ordinal-weekday recurrence, e.g. "first Monday of
     * every month" or, with $byMonth set, "last Friday of November, every year".
     */
    public function withNthWeekday(string $byDay = '1MO', ?int $byMonth = null): static
    {
        return $this->state(fn () => [
            'frequency' => $byMonth ? 'YEARLY' : 'MONTHLY',
            'by_day' => $byDay,
            'by_month' => $byMonth,
        ]);
    }

    /**
     * State for "N days before month end" (0 = the last day of the month).
     */
    public function withDaysBeforeMonthEnd(int $days = 0, ?int $byMonth = null): static
    {
        return $this->state(fn () => [
            'frequency' => $byMonth ? 'YEARLY' : 'MONTHLY',
            'days_before_month_end' => $days,
            'by_month' => $byMonth,
        ]);
    }

    /**
     * State for "last working day of the month".
     */
    public function withLastBusinessDayOfMonth(?int $byMonth = null): static
    {
        return $this->state(fn () => [
            'frequency' => $byMonth ? 'YEARLY' : 'MONTHLY',
            'last_business_day_of_month' => true,
            'by_month' => $byMonth,
        ]);
    }
}
