<?php

namespace App\Providers\Faker;

use App\Providers\Faker\CurrencyData;

class FakeCurrency extends \Faker\Provider\Base
{
    /*
     * Get currency as array with all properties
     *
     * @return array
     */
    public static function currencyArray(): array
    {
        return static::randomElement(CurrencyData::getCurrencies());
    }
}
