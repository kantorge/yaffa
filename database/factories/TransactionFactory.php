<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Transaction;
use Faker\Generator as Faker;

$factory->define(Transaction::class, function (Faker $faker) {
    return [
        "transaction_type_id" => 1, //TODO: random or driven: \App\TransactionType::inRandomOrder()->first(),
        "comment" => $faker->boolean(50) ? $faker->text($maxNbChars = 191)  : null,
        "reconciled" => $faker->boolean(50) ? 1  : 0,
        "config_type" => "transaction_detail_standard",  //TODO: random or driven
        "config_id" => factory(\App\TransactionDetailStandard::class)->create()->id,  //TODO: should be dynamic, based on transaction type
        "date" => $faker->date($format = 'Y-m-d', $max = 'now')
    ];
});
