<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Brick\Money\Money;
use Database\Factories\TransactionDetailStandardFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RuntimeException;

/**
 * App\Models\TransactionDetailStandard
 *
 * @property int $id
 * @property int|null $account_from_id
 * @property int|null $account_to_id
 * @property-read Money $amount_from
 * @property-write Money|string|int|float $amount_from
 * @property-read Money $amount_to
 * @property-write Money|string|int|float $amount_to
 * @property string|null $amount_from_base
 * @property string|null $amount_to_base
 * @property-read AccountEntity|null $accountFrom
 * @property-read AccountEntity|null $accountTo
 * @property-read Transaction|null $config
 * @method static Builder|Transaction isSchedule()
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
 * @mixin Eloquent
 * @property-read Transaction|null $transaction
 * @mixin \Eloquent
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
     * @var list<string>
     */
    protected $fillable = [
        'account_from_id',
        'account_to_id',
        'amount_from',
        'amount_to',
    ];

    protected function casts(): array
    {
        return [
            'amount_from' => MoneyCast::class . ':4,resolveAmountFromCurrency',
            'amount_to' => MoneyCast::class . ':4,resolveAmountToCurrency',
        ];
    }

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

    /**
     * amount_from's currency is accountFrom's currency when accountFrom is a real
     * account; when accountFrom is a payee (deposit), amount_from mirrors amount_to's
     * value, so it takes accountTo's currency instead.
     */
    public function resolveAmountFromCurrency(): Currency
    {
        // Only "config" itself can be eager-loaded across both morph targets (Account and
        // Payee alike) - Payee has no "currency" relation, so it must never be part of a
        // blind loadMissing('config.currency') here, or it throws RelationNotFoundException.
        $this->loadMissing(['accountFrom.config', 'accountTo.config']);

        return $this->resolveStandardCurrency($this->accountFrom, $this->accountTo);
    }

    /**
     * amount_to's currency is accountTo's currency when accountTo is a real account;
     * when accountTo is a payee (withdrawal), amount_to mirrors amount_from's value, so
     * it takes accountFrom's currency instead.
     */
    public function resolveAmountToCurrency(): Currency
    {
        $this->loadMissing(['accountFrom.config', 'accountTo.config']);

        return $this->resolveStandardCurrency($this->accountTo, $this->accountFrom);
    }

    private function resolveStandardCurrency(?AccountEntity $primary, ?AccountEntity $fallback): Currency
    {
        if ($primary?->config instanceof Account) {
            return $primary->config->loadMissing('currency')->currency;
        }

        if ($fallback?->config instanceof Account) {
            return $fallback->config->loadMissing('currency')->currency;
        }

        // Neither side resolved to a real account - e.g. an unvalidated draft/preview
        // referencing an invalid or inaccessible account. Fall back to the owning
        // transaction's currency (itself falling back to the user's base currency),
        // the same fallback Transaction::transaction_currency already provides.
        $currency = $this->transaction?->transaction_currency;

        if ($currency !== null) {
            return $currency;
        }

        throw new RuntimeException('Unable to resolve a currency for this standard transaction detail: neither side is a real account and no fallback transaction currency is available.');
    }
}
