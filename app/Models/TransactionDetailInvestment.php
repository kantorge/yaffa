<?php

namespace App\Models;

use App\Casts\DecimalCast;
use App\Casts\MoneyCast;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Database\Factories\TransactionDetailInvestmentFactory;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RuntimeException;

/**
 * App\Models\TransactionDetailInvestment
 *
 * @property int $id
 * @property int $account_entity_id
 * @property int $investment_id
 * @property-read Money|null $price
 * @property-write Money|string|int|float|null $price
 * @property-read BigDecimal|null $quantity
 * @property-write BigDecimal|string|int|float|null $quantity
 * @property-read Money|null $commission
 * @property-write Money|string|int|float|null $commission
 * @property-read Money|null $tax
 * @property-write Money|string|int|float|null $tax
 * @property-read Money|null $dividend
 * @property-write Money|string|int|float|null $dividend
 * @property-read AccountEntity $account
 * @property-read Transaction|null $config
 * @property-read Investment $investment
 * @method static Builder|Transaction isSchedule()
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
 * @mixin Eloquent
 * @property int $account_id
 * @property-read Transaction|null $transaction
 * @mixin \Eloquent
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
     * @var list<string>
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

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class . ':10,resolveInvestmentCurrency',
            'quantity' => DecimalCast::class . ':4',
            'commission' => MoneyCast::class . ':4,resolveAccountCurrency',
            'tax' => MoneyCast::class . ':4,resolveAccountCurrency',
            'dividend' => MoneyCast::class . ':4,resolveAccountCurrency',
        ];
    }

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'config');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'account_id');
    }

    /**
     * Get the investment details associated with the transaction.
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class, 'investment_id');
    }

    /**
     * price is the investment's own price history value, denominated in the
     * investment's currency (matching investment_prices.price's meaning).
     */
    public function resolveInvestmentCurrency(): Currency
    {
        return $this->loadMissing('investment.currency')->investment->currency;
    }

    /**
     * commission/tax/dividend are cash amounts moving through the investment's cash
     * account, denominated in that account's currency. Per domain rule, this must equal
     * the investment's own currency; if data is corrupted and they differ, arithmetic
     * combining a price-derived Money with these fields will throw rather than silently
     * mixing currencies.
     */
    public function resolveAccountCurrency(): Currency
    {
        // Only "config" itself can be safely eager-loaded across morph targets - Payee has
        // no "currency" relation, so it must never be part of a blind loadMissing('config.currency').
        // Investment transactions are validated to always reference a real Account, but this
        // guards against corrupted data rather than assuming it.
        $config = $this->loadMissing('account.config')->account->config;

        if (! $config instanceof Account) {
            throw new RuntimeException('Unable to resolve a currency for this investment transaction detail: account is not a real account.');
        }

        $config->loadMissing('currency');

        return $config->currency;
    }
}
