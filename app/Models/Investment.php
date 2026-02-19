<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Database\Factories\InvestmentFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * App\Models\Investment
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $symbol
 * @property string|null $isin
 * @property string|null $comment
 * @property bool $active
 * @property bool $auto_update
 * @property string|null $investment_price_provider
 * @property int $investment_group_id
 * @property int $currency_id
 * @property string|null $scrape_url
 * @property string|null $scrape_selector
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Currency $currency
 * @property-read string|null $investment_price_provider_name
 * @property-read InvestmentGroup $investmentGroup
 * @property-read Collection|InvestmentPrice[] $investmentPrices
 * @property-read int|null $investment_prices_count
 * @property-read User $user
 * @method static Builder|Investment active()
 * @method static InvestmentFactory factory(...$parameters)
 * @method static Builder|Investment newModelQuery()
 * @method static Builder|Investment newQuery()
 * @method static Builder|Investment query()
 * @method static Builder|Investment whereActive($value)
 * @method static Builder|Investment whereAutoUpdate($value)
 * @method static Builder|Investment whereComment($value)
 * @method static Builder|Investment whereCreatedAt($value)
 * @method static Builder|Investment whereCurrencyId($value)
 * @method static Builder|Investment whereId($value)
 * @method static Builder|Investment whereInvestmentGroupId($value)
 * @method static Builder|Investment whereInvestmentPriceProvider($value)
 * @method static Builder|Investment whereIsin($value)
 * @method static Builder|Investment whereName($value)
 * @method static Builder|Investment whereSymbol($value)
 * @method static Builder|Investment whereUpdatedAt($value)
 * @method static Builder|Investment whereUserId($value)
 * @property-read Collection<int, TransactionDetailInvestment> $transactionDetailInvestment
 * @property-read int|null $transaction_detail_investment_count
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read Collection<int, Transaction> $transactionsBasic
 * @property array<int, array{date: string, quantity: float, schedule: float}>|null $quantities
 * @property-read int|null $transactions_basic_count
 * @property-read Collection<int, Transaction> $transactionsScheduled
 * @property-read int|null $transactions_scheduled_count
 * @method static Builder<static>|Investment whereScrapeSelector($value)
 * @method static Builder<static>|Investment whereScrapeUrl($value)
 * @mixin Eloquent
 */
class Investment extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $guarded = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'symbol',
        'isin',
        'comment',
        'active',
        'auto_update',
        'investment_group_id',
        'currency_id',
        'investment_price_provider',
        'scrape_url',
        'scrape_selector',
    ];

    protected $appends = [
        'investment_price_provider_name',
    ];

    /**
     * Scope a query to only include active investments.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function transactionDetailInvestment(): HasMany
    {
        return $this->hasMany(TransactionDetailInvestment::class);
    }

    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailInvestment::class,
            'investment_id',
            'config_id',
            'id',
            'id',
        )
            ->where(
                'config_type',
                'investment'
            );
    }

    public function transactionsBasic(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailInvestment::class,
            'investment_id',
            'config_id',
            'id',
            'id',
        )
            ->byScheduleType('none')
            ->where(
                'config_type',
                'investment'
            );
    }

    public function transactionsScheduled(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            TransactionDetailInvestment::class,
            'investment_id',
            'config_id',
            'id',
            'id',
        )
            ->byScheduleType('schedule')
            ->where(
                'config_type',
                'investment'
            );
    }

    public function investmentPrices(): HasMany
    {
        return $this->hasMany(InvestmentPrice::class);
    }

    public function investmentGroup(): BelongsTo
    {
        return $this->belongsTo(InvestmentGroup::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'auto_update' => 'boolean',
        ];
    }

    public function getInvestmentPriceProviderNameAttribute(): ?string
    {
        // If the price provider is not set, return null
        if (! $this->investment_price_provider) {
            return null;
        }

        $registry = app(\App\Services\InvestmentPriceProviderRegistry::class);

        if ($registry->has($this->investment_price_provider)) {
            $metadata = $registry->getMetadata($this->investment_price_provider);

            return $metadata['displayName'];
        }

        return null;
    }

    /**
     * Return all available price providers with all their details.
     */
    public function getAllInvestmentPriceProviders(): array
    {
        $registry = app(\App\Services\InvestmentPriceProviderRegistry::class);

        return $registry->getAllMetadata();
    }
}
