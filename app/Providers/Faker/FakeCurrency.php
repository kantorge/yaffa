<?php

namespace App\Providers\Faker;

class FakeCurrency extends \Faker\Provider\Base
{
    /**
     * Get a random currency from the predefined pool as an array with all properties
     */
    public static function currencyArray(): array
    {
        return static::randomElement(CurrencyData::getCurrencies());
    }

    /**
     * Get the details of a currency by its ISO code
     */
    public static function currencyArrayByIsoCode(string $isoCode): array|null
    {
        return CurrencyData::getCurrencyByIsoCode($isoCode);
    }
}
