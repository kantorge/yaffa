<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TransactionDetailInvestment
 *
 * @property int $id
 * @property int $account_id
 * @property int $investment_id
 * @property float|null $price
 * @property float|null $quantity
 * @property float|null $commission
 * @property float|null $tax
 * @property float|null $dividend
 * @property-read \App\Models\AccountEntity $account
 * @property-read \App\Models\Transaction|null $config
 * @property-read \App\Models\Investment $investment
 * @method static \Illuminate\Database\Eloquent\Builder|Transaction byScheduleType($type)
 * @method static \Database\Factories\TransactionDetailInvestmentFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereDividend($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereInvestmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionDetailInvestment whereTax($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read \App\Models\TransactionSchedule|null $transactionSchedule
 * @property-read \App\Models\TransactionType $transactionType
 */
class TransactionDetailInvestment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_details_investment';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_id',
        'investment_id',
        'price',
        'quantity',
        'commission',
        'tax',
        'dividend',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'investment_id' => 'integer',
        'price' => 'float',
        'quantity' => 'float',
        'commission' => 'float',
        'tax' => 'float',
        'dividend' => 'float',
    ];

    public function config()
    {
        return $this->morphOne(Transaction::class, 'config');
    }

    public function account()
    {
        return $this->belongsTo(AccountEntity::class, 'account_id');
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }
}
