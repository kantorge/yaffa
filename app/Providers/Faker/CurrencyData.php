<?php

namespace App\Providers\Faker;

class CurrencyData
{
    protected static array $currencies = [
        [
            'name' => 'US Dollar',
            'iso_code' => 'USD',
        ],
        [
            'name' => 'Hungarian Forint',
            'iso_code' => 'HUF',
        ],
        [
            'name' => 'Euro',
            'iso_code' => 'EUR',
        ],
        [
            'name' => 'Polish Złoty',
            'iso_code' => 'PLN',
        ],
    ];

    public static function getCurrencies(): array
    {
        return static::$currencies;
    }

    public static function getCurrencyByIsoCode(string $isoCode): array|null
    {
        foreach (static::getCurrencies() as $currency) {
            if ($currency['iso_code'] === $isoCode) {
                return $currency;
            }
        }

        return null;
    }

    public static function getRandomIsoCode(): string
    {
        $currencies = CurrencyData::getCurrencies();
        $currency = array_rand($currencies);
        return $currencies[$currency]['iso_code'];
    }
}
