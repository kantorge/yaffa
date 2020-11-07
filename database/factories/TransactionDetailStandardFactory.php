<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\AccountEntity;
use App\Model;
use App\TransactionDetailStandard;
use Faker\Generator as Faker;

$factory->define(TransactionDetailStandard::class, function (Faker $faker) {
    return [];
});

$factory->state(TransactionDetailStandard::class, 'withdrawal', function() {
    return [
        "amount_from" => 0, //TODO: make dynamic
        "amount_to" => 0, //TODO: make dynamic
        "account_from_id" => AccountEntity::where('config_type', 'account')->inRandomOrder()->first()->id,
        "account_to_id" => AccountEntity::where('config_type', 'payee')->inRandomOrder()->first()->id,
    ];
});

$factory->state(TransactionDetailStandard::class, 'deposit', function() {
    return [
        "amount_from" => 0, //TODO: make dynamic
        "amount_to" => 0, //TODO: make dynamic
        "account_from_id" => AccountEntity::where('config_type', 'payee')->inRandomOrder()->first()->id,
        "account_to_id" => AccountEntity::where('config_type', 'account')->inRandomOrder()->first()->id,
    ];
});

$factory->state(TransactionDetailStandard::class, 'transfer', function(Faker $faker) {
    $accounts = AccountEntity::where('config_type', 'account')->inRandomOrder()->take(2)->get();
    $amount = $faker->numberBetween($min = 1, $max = 100);

    return [
        "amount_from" => $amount,
        "amount_to" => $amount, //TODO: account for currency differencies
        "account_from_id" => $accounts->slice(0, 1)->first()->id,
        "account_to_id" => $accounts->slice(1, 1)->first()->id,
    ];
});