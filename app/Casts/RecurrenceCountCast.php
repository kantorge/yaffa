<?php

namespace App\Casts;

use App\Models\Concerns\HasRecurrenceRule;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Backs TransactionSchedule/Budget's virtual `count` recurrence field (the RFC 5545 COUNT
 * component, decomposed from/composed into `rrule` - see HasRecurrenceRule) via an explicit cast
 * class instead of HasRecurrenceRule's usual auto-detected `{camelKey}(): Attribute` accessor
 * method convention.
 *
 * `count` can't use that convention: Eloquent's Model::__callStatic() implements every
 * undefined static call (including the aggregate query method `Budget::count()`/
 * `TransactionSchedule::count()`) as `(new static)->$method(...)` - if the model defined a real
 * instance method named `count()` (which the Attribute-accessor convention would require), that
 * call would silently invoke this accessor instead of forwarding to the query builder, breaking
 * every `count()` call on either model.
 *
 * @implements CastsAttributes<int|null, int|null>
 */
class RecurrenceCountCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        /** @var Model&HasRecurrenceRule $model */
        return $model->getRecurrenceField('count', fn ($rule) => $rule->getCount());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        /** @var Model&HasRecurrenceRule $model */
        $model->setPendingRecurrenceField('count', $value);

        return [];
    }
}
