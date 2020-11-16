<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\AccountEntity;
use App\Investment;
use App\Model;
use App\TransactionDetailInvestment;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Builder;

$factory->define(TransactionDetailInvestment::class, function (Faker $faker) {
    //TODO: investment és account egy currency-ből legyen, random választással
    return [
        //"account_id" => AccountEntity::where('config_type', 'account')->inRandomOrder()->first()->id,
        "account_id" => AccountEntity::where('config_type', 'account')
            ->whereHasMorph(
                'config',
                [\App\Account::class],
                function (Builder $query) {
                    $query->where('currencies_id', 1);
                }
            )
            ->inRandomOrder()
            ->first()
            ->id,
        "investment_id" => Investment::where('currencies_id', 1)
            ->inRandomOrder()
            ->first()
            ->id,
    ];
});

$factory->state(TransactionDetailInvestment::class, 'buy', function(Faker $faker) {
    return [
        "price" => $faker->randomFloat($nbMaxDecimals = 4, $min = 0.0001, $max = 100),  //TODO: dynamic based on investment price
        "quantity" => $faker->randomFloat($nbMaxDecimals = 4, $min = 1, $max = 100),
        "commission" => $faker->randomFloat($nbMaxDecimals = 4, $min = 0.0001, $max = 100),
        "dividend" => 0,
    ];
});