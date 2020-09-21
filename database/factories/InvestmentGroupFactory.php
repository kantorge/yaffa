<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\InvestmentGroup;
use App\Model;
use Faker\Generator as Faker;

$factory->define(InvestmentGroup::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->text(mt_rand(10, 50)),
    ];
});
