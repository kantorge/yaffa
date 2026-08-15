<?php

namespace Tests\Unit\Support;

use App\Support\ScheduleInstance;
use Brick\Math\BigDecimal;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money;
use Tests\TestCase;

/**
 * ScheduleInstance::toArray() must unwrap Money/BigDecimal the same way MoneyCast/DecimalCast
 * do (a decimal string), since a forecast instance's cashflow_value sits under the same JSON
 * key as a real (Eloquent, MoneyCast-serialized) transaction's - a mismatched shape here would
 * break any frontend code expecting a decimal string on every row of that payload.
 */
class ScheduleInstanceTest extends TestCase
{
    public function test_to_array_serializes_money_as_a_decimal_string(): void
    {
        $money = Money::of('123.4500', new BrickCurrency('USD', 0, 'USD', 4));

        $instance = new ScheduleInstance(['cashflow_value' => $money]);

        $this->assertSame('123.4500', $instance->toArray()['cashflow_value']);
    }

    public function test_to_array_serializes_big_decimal_as_a_decimal_string(): void
    {
        $quantity = BigDecimal::of('10.0000');

        $instance = new ScheduleInstance(['quantity' => $quantity]);

        $this->assertSame('10.0000', $instance->toArray()['quantity']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $money = Money::of('50.0000', new BrickCurrency('EUR', 0, 'EUR', 4));

        $instance = new ScheduleInstance(['cashflow_value' => $money]);

        $this->assertSame('50.0000', $instance->jsonSerialize()['cashflow_value']);
    }

    public function test_to_array_leaves_null_money_untouched(): void
    {
        $instance = new ScheduleInstance(['cashflow_value' => null]);

        $this->assertNull($instance->toArray()['cashflow_value']);
    }
}
