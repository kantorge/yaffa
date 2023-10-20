<?php

namespace Database\Factories;

use App\Models\TransactionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionScheduleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TransactionSchedule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 year', 'now');
        $end = $this->faker->dateTimeBetween($start, $start->format('Y-m-d') . ' +2 year');

        return [
            'start_date' => $start,
            'next_date' => $start,
            'end_date' => $end,
            'frequency' => $this->faker->randomElement(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
            'interval' => $this->faker->numberBetween(1, 5),
            'count' => $this->faker->boolean(50) ? null : $this->faker->numberBetween(1, 5),
            'automatic_recording' => false,
        ];
    }
}
