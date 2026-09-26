<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Casts\MoneyCast;
use App\Http\Controllers\API\TransactionApiController;
use App\Models\Currency;
use Brick\Money\Money;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Coverage for TransactionApiController::convertToBase(), the Money::convertedTo()-based
 * replacement for the old `amount * rate` currency conversion (FR-5).
 */
class TransactionApiControllerConversionTest extends TestCase
{
    private function convertToBase(Money $amount, Currency $baseCurrency, float $rate): string
    {
        $method = new ReflectionMethod(TransactionApiController::class, 'convertToBase');
        $method->setAccessible(true);

        return $method->invoke(app(TransactionApiController::class), $amount, $baseCurrency, $rate);
    }

    private function money(string $amount, string $isoCode, int $scale = 4): Money
    {
        return Money::of($amount, MoneyCast::currencyFor(
            Currency::factory()->make(['iso_code' => $isoCode]),
            $scale
        ));
    }

    /**
     * A representative rate: matches what the previous `amount * rate` float
     * multiplication would have produced, now computed exactly.
     */
    public function test_converted_amount_matches_the_expected_product_for_a_representative_rate(): void
    {
        $amount = $this->money('123.4567', 'USD');
        $baseCurrency = Currency::factory()->make(['iso_code' => 'EUR']);

        $result = $this->convertToBase($amount, $baseCurrency, 1.05);

        // 123.4567 * 1.05 = 129.629535, which rounds down to 129.6295 (5th decimal is 3).
        $this->assertSame('129.6295', $result);
    }

    /**
     * An exact tie at the smallest configured decimal place (the 5th decimal digit is
     * exactly 5) must round away from zero (HALF_UP), not to even and not down.
     */
    public function test_converted_amount_rounds_a_tie_away_from_zero(): void
    {
        $amount = $this->money('7.0000', 'USD');
        $baseCurrency = Currency::factory()->make(['iso_code' => 'EUR']);

        // 7 * 0.00005 = 0.00035 exactly - a tie at the 4th decimal place.
        $result = $this->convertToBase($amount, $baseCurrency, 0.00005);

        $this->assertSame('0.0004', $result);
    }

    public function test_converted_amount_reuses_the_source_scale(): void
    {
        $amount = $this->money('100.0000000000', 'USD', 10);
        $baseCurrency = Currency::factory()->make(['iso_code' => 'EUR']);

        $result = $this->convertToBase($amount, $baseCurrency, 2);

        $this->assertSame('200.0000000000', $result);
    }
}
