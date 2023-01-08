<?php

namespace App\Providers\Faker;

class CurrencyData
{
    protected static $currencies = [
        [
            'name' => 'US Dollar',
            'isoCode' => 'USD',
            'symbol' => '$',
            'minorUnits' => '2',
        ],
        [
            'name' => 'Hungarian Forint',
            'isoCode' => 'HUF',
            'symbol' => 'Ft',
            'minorUnits' => '0',
        ],
        [
            'name' => 'Euro',
            'isoCode' => 'EUR',
            'symbol' => '€',
            'minorUnits' => '2',
        ],
        [
            'name' => 'Polish Złoty',
            'isoCode' => 'PLN',
            'symbol' => 'zł',
            'minorUnits' => '2',
        ],
    ];

    public static function getCurrencies(): array
    {
        return static::$currencies;
    }
}
