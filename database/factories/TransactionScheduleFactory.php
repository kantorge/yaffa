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

        // count and end_date are mutually exclusive (backend enforces this via
        // `prohibits`), so only one of the two may be set here.
        $hasCount = $this->faker->boolean(50);

        return [
            'start_date' => $start,
            'next_date' => $start,
            'end_date' => $hasCount ? null : $end,
            'frequency' => $this->faker->randomElement(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
            'interval' => $this->faker->numberBetween(1, 5),
            'count' => $hasCount ? $this->faker->numberBetween(1, 5) : null,
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
}
