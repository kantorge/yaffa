<?php

use App\Support\ScheduleInstance;
use Brick\Math\BigDecimal;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money;

/**
 * ScheduleInstance::toArray() must unwrap Money/BigDecimal the same way MoneyCast/DecimalCast
 * do (a decimal string): a forecast instance's cashflow_value sits under the same JSON key as
 * a real (MoneyCast-serialized) transaction's.
 */
it('serializes money and decimals as decimal strings and leaves null untouched', function (string $key, ?string $value, ?string $expected) {
    $value = match (true) {
        $value === null => null,
        $key === 'quantity' => BigDecimal::of($value),
        default => Money::of($value, new BrickCurrency('USD', 0, 'USD', 4)),
    };
    $instance = new ScheduleInstance([$key => $value]);

    expect($instance->toArray()[$key])->toBe($expected)
        ->and($instance->jsonSerialize()[$key])->toBe($expected);
})->with([
    'money' => ['cashflow_value', '123.4500', '123.4500'],
    'big decimal' => ['quantity', '10.0000', '10.0000'],
    'null money' => ['cashflow_value', null, null],
]);
