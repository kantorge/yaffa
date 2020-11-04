<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Category;
use App\TransactionItem;
use Faker\Generator as Faker;

$factory->define(TransactionItem::class, function (Faker $faker) {
    return [
        'category_id'   => Category::inRandomOrder()->first()->id,
        'amount'        => $faker->numberBetween($min = 1, $max = 100),
        'comment'       => $faker->boolean(50) ? $faker->text($maxNbChars = 191)  : null,
    ];
});
