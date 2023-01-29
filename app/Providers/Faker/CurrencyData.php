<?php

namespace App\Providers\Faker;

class CurrencyData
{
    protected static array $currencies = [
        [
            'name' => 'US Dollar',
            'isoCode' => 'USD',
            'symbol' => '$',
            'minorUnits' => 2,
        ],
        [
            'name' => 'Hungarian Forint',
            'isoCode' => 'HUF',
            'symbol' => 'Ft',
            'minorUnits' => 0,
        ],
        [
            'name' => 'Euro',
            'isoCode' => 'EUR',
            'symbol' => '€',
            'minorUnits' => 2,
        ],
        [
            'name' => 'Polish Złoty',
            'isoCode' => 'PLN',
            'symbol' => 'zł',
            'minorUnits' => 2,
        ],
    ];

    public static function getCurrencies(): array
    {
        return static::$currencies;
    }

    public static function getCurrencyByIsoCode(string $isoCode): array|null
    {
        foreach(static::getCurrencies() as $currency)
        {
            if ($currency['isoCode'] === $isoCode )
                return $currency;
        }

        return null;
    }

    public static function getRandomIsoCode(): string
    {
        $currencies = CurrencyData::getCurrencies();
        $currency = array_rand($currencies);
        return $currencies[$currency]['isoCode'];
    }
}
