<?php

namespace App\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a DECIMAL column that is a quantity or ratio (not an actual currency amount -
 * e.g. a share count or an exchange rate) to/from a Brick\Math\BigDecimal, at a fixed
 * scale matching the column's own DECIMAL(x,scale) definition.
 */
class DecimalCast implements CastsAttributes, SerializesCastableAttributes
{
    public function __construct(private readonly int $scale)
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        return $value === null ? null : BigDecimal::of($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $decimal = $value instanceof BigDecimal ? $value : BigDecimal::of((string) $value);

        // HALF_UP tolerates over-precise input until FR-6 adds real input clamping in the
        // UI layer; read (get()) never rounds, since a DB value is already at-scale.
        return [$key => (string) $decimal->toScale($this->scale, RoundingMode::HalfUp)];
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof BigDecimal ? (string) $value : $value;
    }

    /**
     * Unwrap a BigDecimal back to a float, for consumers not yet migrated to exact arithmetic.
     */
    public static function toFloat(?BigDecimal $value): ?float
    {
        return $value?->toFloat();
    }
}
