<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\TransactionDetailStandard;
use Faker\Generator as Faker;

$factory->define(TransactionDetailStandard::class, function (Faker $faker) {
    return [
        "account_from_id" => 2, //TODO: make dynamic based on transaction type
        "account_to_id" => 3,  //TODO: make dynamic based on transaction type
        "amount_from" => 0,  //TODO: make dynamic
        "amount_to" => 0  //TODO: make dynamic
    ];

});
