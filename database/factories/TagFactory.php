<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Tag;
use App\Model;
use Faker\Generator as Faker;

$factory->define(Tag::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->text(mt_rand(10, 50)),
    ];
});
