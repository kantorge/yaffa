<?php

namespace App\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

        // HALF_UP here (rather than the stricter UNNECESSARY used on read) tolerates
        // over-precise input until FR-6 adds real input clamping in the UI layer.
        return [$key => (string) $decimal->toScale($this->scale, RoundingMode::HalfUp)];
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof BigDecimal ? (string) $value : $value;
    }

    /**
     * Unwrap a BigDecimal back to a float, for consumers not yet migrated to exact arithmetic.
     *
     * ponytail: logs every call, no dedup - fine for locating remaining Phase 4 call
     * sites, but will be noisy on a hot loop (e.g. CalculateAccountMonthlySummary).
     * Add per-caller dedup (or drop to Log::debug) if this gets noisy in practice.
     */
    public static function toFloat(?BigDecimal $value): ?float
    {
        if ($value !== null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? [];

            Log::warning('DecimalCast::toFloat() called by a not-yet-migrated consumer', [
                'caller' => isset($caller['class'])
                    ? $caller['class'] . ($caller['type'] ?? '::') . $caller['function']
                    : ($caller['function'] ?? 'unknown'),
                'file' => $trace[0]['file'] ?? null,
                'line' => $trace[0]['line'] ?? null,
            ]);
        }

        return $value?->toFloat();
    }
}
