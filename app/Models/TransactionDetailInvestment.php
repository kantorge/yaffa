<?php

namespace App\Models;

use Database\Factories\TransactionDetailInvestmentFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * App\Models\TransactionDetailInvestment
 *
 * @property int $id
 * @property int $account_entity_id
 * @property int $investment_id
 * @property float|null $price
 * @property float|null $quantity
 * @property float|null $commission
 * @property float|null $tax
 * @property float|null $dividend
 * @property-read AccountEntity $account
 * @property-read Transaction|null $config
 * @property-read Investment $investment
 * @method static Builder|Transaction byScheduleType($type)
 * @method static TransactionDetailInvestmentFactory factory(...$parameters)
 * @method static Builder|TransactionDetailInvestment newModelQuery()
 * @method static Builder|TransactionDetailInvestment newQuery()
 * @method static Builder|TransactionDetailInvestment query()
 * @method static Builder|TransactionDetailInvestment whereAccountId($value)
 * @method static Builder|TransactionDetailInvestment whereCommission($value)
 * @method static Builder|TransactionDetailInvestment whereDividend($value)
 * @method static Builder|TransactionDetailInvestment whereId($value)
 * @method static Builder|TransactionDetailInvestment whereInvestmentId($value)
 * @method static Builder|TransactionDetailInvestment wherePrice($value)
 * @method static Builder|TransactionDetailInvestment whereQuantity($value)
 * @method static Builder|TransactionDetailInvestment whereTax($value)
 * @property-read Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 * @mixin Eloquent
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
     * @var array<string>
     */
    protected $fillable = [
        'account_entity_id',
        'investment_id',
        'price',
        'quantity',
        'commission',
        'tax',
        'dividend',
    ];

    protected $casts = [
        'price' => 'float',
        'quantity' => 'float',
        'commission' => 'float',
        'tax' => 'float',
        'dividend' => 'float',
    ];

    public function config(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'config');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_entity_id');
    }

    /**
     * Get the investment details associated with the transaction.
     *
     * @return BelongsTo
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }
}
