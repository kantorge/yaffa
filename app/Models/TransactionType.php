<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TransactionType
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int|null $amount_multiplier
 * @property int|null $quantity_multiplier
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereAmountOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereQuantityOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereType($value)
 * @mixin \Eloquent
 */
class TransactionType extends Model
{
    public $timestamps = false;
    protected $table = 'transaction_types';

    protected $guarded = [
        'amount_multiplier',
        'quantity_multiplier',
    ];

    protected $casts = [
        'amount_multiplier' => 'integer',
        'quantity_multiplier' => 'integer',
    ];
}
