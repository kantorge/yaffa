<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\TransactionSchedule;
use Faker\Generator as Faker;

$factory->define(TransactionSchedule::class, function (Faker $faker) {
    $start = $faker->dateTimeBetween($startDate = '-1 year', $endDate = 'now');
    $end = $faker->dateTimeBetween($start, $start->format('Y-m-d H:i:s').' 2 years');

    return [
        'start_date' => $start,
        'next_date' => $start,
        'end_date' => $end,
        'frequency' => $faker->randomElement(['DAILY', 'WEEKLY', 'MOHTHLY', 'YEARLY']),
        'interval' => $faker->numberBetween($min = 1, $max = 5),
        'count' => $faker->boolean(50) ? null  : $faker->numberBetween($min = 1, $max = 5),
    ];
});
