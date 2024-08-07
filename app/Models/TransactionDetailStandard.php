<?php

namespace App\Models;

use Database\Factories\TransactionDetailStandardFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * App\Models\TransactionDetailStandard
 *
 * @property int $id
 * @property int|null $account_from_id
 * @property int|null $account_to_id
 * @property float $amount_from
 * @property float $amount_to
 * @property-read AccountEntity|null $accountFrom
 * @property-read AccountEntity|null $accountTo
 * @property-read Transaction|null $config
 * @method static Builder|Transaction byScheduleType($type)
 * @method static TransactionDetailStandardFactory factory(...$parameters)
 * @method static Builder|TransactionDetailStandard newModelQuery()
 * @method static Builder|TransactionDetailStandard newQuery()
 * @method static Builder|TransactionDetailStandard query()
 * @method static Builder|TransactionDetailStandard whereAccountFromId($value)
 * @method static Builder|TransactionDetailStandard whereAccountToId($value)
 * @method static Builder|TransactionDetailStandard whereAmountFrom($value)
 * @method static Builder|TransactionDetailStandard whereAmountTo($value)
 * @method static Builder|TransactionDetailStandard whereId($value)
 * @property-read Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 * @mixin Eloquent
 */
class TransactionDetailStandard extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_details_standard';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'account_from_id',
        'account_to_id',
        'amount_from',
        'amount_to',
    ];

    protected $casts = [
        'amount_from' => 'float',
        'amount_to' => 'float',
    ];

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'config');
    }

    public function accountFrom(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_from_id');
    }

    public function accountTo(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_to_id');
    }
}
