<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Transaction;
use App\TransactionDetailStandard;
use App\TransactionType;
use Faker\Generator as Faker;

$factory->define(Transaction::class, function (Faker $faker) {
    return [
        "comment" => $faker->boolean(50) ? $faker->text($maxNbChars = 191)  : null,
        "reconciled" => $faker->boolean(50) ? 1  : 0,
        "config_type" => "transaction_detail_standard",  //TODO: random or driven
        "date" => $faker->dateTimeBetween($startDate = '-1 year', $endDate = 'now'),
    ];
});

$factory->state(Transaction::class, 'withdrawal', function() {
    return [
        "transaction_type_id" => TransactionType::where('name', 'withdrawal')->first()->id,
        "config_id" => factory(TransactionDetailStandard::class)->states('withdrawal')->create()->id
    ];
});

$factory->state(Transaction::class, 'deposit', function() {
    return [
        "transaction_type_id" => TransactionType::where('name', 'deposit')->first()->id,
        "config_id" => factory(TransactionDetailStandard::class)->states('deposit')->create()->id
    ];
});

$factory->state(Transaction::class, 'transfer', function() {
    return [
        "transaction_type_id" => TransactionType::where('name', 'transfer')->first()->id,
        "config_id" => factory(TransactionDetailStandard::class)->states('transfer')->create()->id
    ];
});