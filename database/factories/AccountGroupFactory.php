<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\AccountGroup;
use App\Model;
use Faker\Generator as Faker;

$factory->define(AccountGroup::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->text(mt_rand(10, 50)),
    ];
});
