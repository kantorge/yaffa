<?php

namespace App\Support;

use BackedEnum;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A non-Eloquent stand-in for a single virtual (never-persisted) occurrence of a recurring
 * Transaction, produced by Transaction::scheduleInstances().
 *
 * A forecast run can generate thousands of these per job (one per calendar occurrence of
 * every active schedule), and they only ever get summed, grouped, or displayed - never
 * saved - so this deliberately skips Eloquent's per-instance cost (attribute casting,
 * relation resolution, model events) that Transaction::replicate() previously paid on every
 * occurrence. See forecast-performance.md / specification.md FR-9.
 *
 * Behaves like a mutable attribute bag (arbitrary get/set/isset) because calling code
 * routinely stashes extra, non-schema values onto the source Transaction before generating
 * instances (a computed category sum, a running total, display-only fields such as
 * account_from_name) and expects every occurrence to carry them - mirroring what
 * Transaction::replicate() already did by copying the model's raw attributes wholesale.
 *
 * @property-read \App\Models\TransactionDetailStandard|\App\Models\TransactionDetailInvestment|null $config
 * @property-read \Illuminate\Support\Carbon $date
 * @property-read \App\Enums\TransactionType $transaction_type
 * @property-read bool $schedule
 * @property-read bool $budget
 * @property-read bool $reconciled
 * @property-read Money|null $cashflow_value
 * @property-read int|null $currency_id
 * @property-read int $originalId
 * @property-read string $transactionGroup
 * @property-read bool $schedule_first_instance
 */
final class ScheduleInstance implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $attributes  Plain, already-resolved (cast) values. Mutable:
     *                                             callers may stash further ad-hoc values (e.g. running_total).
     * @param  array<string, mixed>  $relations  Already-loaded Eloquent relations (e.g. config,
     *                                           transactionSchedule, currency), shared by reference
     *                                           across every occurrence of the same source transaction.
     */
    public function __construct(
        private array $attributes,
        private readonly array $relations = [],
    ) {
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->relations[$name]) || isset($this->attributes[$name]);
    }

    public function toArray(): array
    {
        $array = $this->attributes;

        foreach ($this->relations as $key => $value) {
            $array[$key] = $value;
        }

        foreach ($array as $key => $value) {
            if ($value instanceof BackedEnum) {
                $array[$key] = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $array[$key] = \Illuminate\Support\Carbon::instance($value)->toJSON();
            } elseif ($value instanceof Money) {
                // Matches MoneyCast::serialize() - a decimal string, not the
                // {"amount":...,"currency":...} shape Money's own JsonSerializable emits.
                $array[$key] = (string) $value->getAmount();
            } elseif ($value instanceof BigDecimal) {
                // Matches DecimalCast::serialize().
                $array[$key] = (string) $value;
            } elseif ($value instanceof Arrayable) {
                $array[$key] = $value->toArray();
            }
        }

        return $array;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
