<?php

namespace App\Providers\Faker;

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
