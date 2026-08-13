<?php

namespace App\Casts;

use App\Models\Currency as YaffaCurrency;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Casts a DECIMAL column holding an actual currency amount to/from a Brick\Money\Money
 * instance, at a fixed scale matching the column's own DECIMAL(x,scale) definition.
 *
 * The currency is resolved by calling a method (named by $currencyMethod) on the owning
 * model, since the currency of a money field is generally determined by a relation
 * (the parent transaction's currency, the account's currency, the investment's currency),
 * not a sibling column.
 */
class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    public function __construct(
        private readonly int $scale,
        private readonly string $currencyMethod,
    ) {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::of($value, $this->resolveCurrency($model));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $amount = $value instanceof Money ? $value->getAmount() : BigDecimal::of((string) $value);

        // HALF_UP here (rather than the stricter UNNECESSARY used on read) tolerates
        // over-precise input until FR-6 adds real input clamping in the UI layer.
        return [$key => (string) $amount->toScale($this->scale, RoundingMode::HalfUp)];
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof Money ? (string) $value->getAmount() : $value;
    }

    /**
     * Build an ad-hoc Brick\Money currency for a YAFFA Currency at a given scale.
     *
     * YAFFA currencies are user-defined (not necessarily real ISO 4217 codes), so this
     * never goes through brick/money's ISO currency registry.
     */
    public static function currencyFor(YaffaCurrency $currency, int $scale): BrickCurrency
    {
        return new BrickCurrency($currency->iso_code, 0, $currency->iso_code, $scale);
    }

    /**
     * Unwrap a Money back to a float, for consumers not yet migrated to exact arithmetic.
     *
     * ponytail: logs every call, no dedup - fine for locating remaining Phase 4 call
     * sites, but will be noisy on a hot loop (e.g. CalculateAccountMonthlySummary).
     * Add per-caller dedup (or drop to Log::debug) if this gets noisy in practice.
     */
    public static function toFloat(?Money $money): ?float
    {
        if ($money !== null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? [];

            Log::warning('MoneyCast::toFloat() called by a not-yet-migrated consumer', [
                'caller' => isset($caller['class'])
                    ? $caller['class'] . ($caller['type'] ?? '::') . $caller['function']
                    : ($caller['function'] ?? 'unknown'),
                'file' => $trace[0]['file'] ?? null,
                'line' => $trace[0]['line'] ?? null,
            ]);
        }

        return $money?->getAmount()->toFloat();
    }

    private function resolveCurrency(Model $model): BrickCurrency
    {
        $currency = $model->{$this->currencyMethod}();

        if (! $currency instanceof YaffaCurrency) {
            throw new InvalidArgumentException(
                sprintf('MoneyCast could not resolve a Currency via %s::%s().', $model::class, $this->currencyMethod)
            );
        }

        return self::currencyFor($currency, $this->scale);
    }
}
