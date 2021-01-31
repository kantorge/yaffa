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
     *
     * @return array
     */
    public function definition()
    {
        $start = $this->faker->dateTimeBetween($startDate = '-1 year', $endDate = 'now');
        $end = $this->faker->dateTimeBetween($start, $start->format('Y-m-d H:i:s').' 2 years');

        return [
            'start_date' => $start,
            'next_date' => $start,
            'end_date' => $end,
            'frequency' => $this->faker->randomElement(['DAILY', 'WEEKLY', 'MOHTHLY', 'YEARLY']),
            'interval' => $this->faker->numberBetween($min = 1, $max = 5),
            'count' => $this->faker->boolean(50) ? null  : $this->faker->numberBetween($min = 1, $max = 5),
        ];
    }
}
