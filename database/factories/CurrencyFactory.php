<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Currency;
use App\Model;
use Faker\Generator as Faker;

$factory->define(Currency::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->text(mt_rand(10, 50)),
        'iso_code' => $faker->unique()->currencyCode(),
        'num_digits' => $faker->numberBetween(0, 2),
        'suffix' => $faker->unique()->text(5),
    ];
});
