<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Carbon\Carbon;
use Database\Factories\InvestmentFactory;
use Eloquent;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Arr;

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
 * @property-read int|null $transactions_basic_count
 * @property-read Collection<int, Transaction> $transactionsScheduled
 * @property-read int|null $transactions_scheduled_count
 * @mixin Eloquent
 */
class Investment extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'investments';

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
    ];

    protected $casts = [
        'active' => 'boolean',
        'auto_update' => 'boolean',
    ];

    protected $appends = [
        'investment_price_provider_name',
    ];

    /**
     * Scope a query to only include active investments.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
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
                'transaction_detail_investment'
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
                'transaction_detail_investment'
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
                'transaction_detail_investment'
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

    public function getCurrentQuantity(AccountEntity $account = null)
    {
        $investmentId = $this->id;

        // Get all investment transactions for current investment
        $transactions = Transaction::with(
            [
                'config',
                'transactionType',
            ]
        )
            ->byScheduleType('none')
            ->where('config_type', 'transaction_detail_investment')
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($investmentId, $account) {
                    $query->Where('investment_id', $investmentId);
                    if ($account !== null) {
                        $query->where('account_id', '=', $account->id);
                    }
                }
            )
            ->get();

        return $transactions->sum(function ($transaction) {
            $operator = $transaction->transactionType->quantity_operator;
            if (! $operator) {
                return 0;
            }

            return $transaction->config->quantity * ($operator === 'minus' ? -1 : 1);
        });
    }

    public function getLatestPrice($type = 'combined', Carbon $onOrBefore = null)
    {
        $investmentId = $this->id;

        if ($type === 'stored' || $type === 'combined') {
            $price = InvestmentPrice::where('investment_id', $investmentId)
                ->when($onOrBefore, function ($query) use ($onOrBefore) {
                    $query->where('date', '<=', $onOrBefore);
                })
                ->latest('date')
                ->first();
        }

        if ($type === 'transaction' || $type === 'combined') {
            $transaction = Transaction::with(
                [
                    'config',
                ]
            )
                ->byScheduleType('none')
                ->whereHasMorph(
                    'config',
                    [TransactionDetailInvestment::class],
                    function (Builder $query) use ($investmentId) {
                        $query
                            ->Where('investment_id', $investmentId)
                            ->WhereNotNull('price');
                    }
                )
                ->when($onOrBefore, function ($query) use ($onOrBefore) {
                    $query->where('date', '<=', $onOrBefore);
                })
                ->latest('date')
                ->first();
        }

        if ($type === 'stored') {
            return $price instanceof InvestmentPrice ? $price->price : null;
        }

        if ($type === 'transaction') {
            return $transaction instanceof Transaction ? $transaction->config->price : null;
        }

        // Combined is needed and we have both data: get latest
        if (($price instanceof InvestmentPrice) && ($transaction instanceof Transaction)) {
            if ($price->date > $transaction->date) {
                return $price->price;
            }

            return $transaction->config->price;
        }

        // We have only stored data
        if ($price instanceof InvestmentPrice) {
            return $price->price;
        }

        // We have only transaction data
        if ($transaction instanceof Transaction) {
            return $transaction->config->price;
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @var array
     */
    protected array $priceProviders = [
        'alpha_vantage' => [
            'name' => 'Alpha Vantage',
        ],
    ];

    /**
     * @return string|null
     */
    public function getInvestmentPriceProviderNameAttribute(): ?string
    {
        // If the price provider is not set, return null
        if (! $this->investment_price_provider) {
            return null;
        }

        $provider = Arr::get($this->priceProviders, $this->investment_price_provider);
        if ($provider) {
            return $provider['name'];
        }

        return null;
    }

    /**
     * Return all available price providers
     *
     * @return array
     */
    public function getAllInvestmentPriceProviders(): array
    {
        return $this->priceProviders;
    }

    /**
     * Common function to get price of investment from provider.
     * It invokes the provider's function and updates the price in the database.
     *
     * @param Carbon|null $from Optionnally specify the date to retrieve data from
     * @param bool $refill Future option to request reload of prices
     * @uses getInvestmentPriceFromAlphaVantage()
     */
    public function getInvestmentPriceFromProvider(Carbon $from = null, bool $refill = false): void
    {
        $providerSuffix = 'getInvestmentPriceFrom' . str_replace([' ', '_'], '', ucwords($this->investment_price_provider_name, '_'));
        $this->{$providerSuffix}($from, $refill);
    }

    /**
     * TODO: this should have a contract to have standard parameters
     * @param Carbon|null $from Optionnally specify the date to retrieve data from
     * @param bool $refill Future option to request reload of prices
     * @throws GuzzleException
     */
    public function getInvestmentPriceFromAlphaVantage(Carbon $from = null, bool $refill = false): void
    {
        // Get 3 days data by default, assuming that scheduler is running
        if (! $from) {
            $from = Carbon::now()->subDays(3);
        }

        $client = new GuzzleClient();

        $response = $client->request(
            'GET',
            'https://www.alphavantage.co/query',
            [
                'query' => [
                    'function' => 'TIME_SERIES_DAILY',
                    'datatype' => 'json',
                    'symbol' => $this->symbol,
                    'apikey' => config('yaffa.alpha_vantage_key'),
                    'outputsize' => ($refill ? 'full' : 'compact'),
                ],
            ]
        );

        $obj = json_decode($response->getBody());

        foreach ($obj->{'Time Series (Daily)'} as $date => $daily_data) {
            if ($from->gt(Carbon::createFromFormat('Y-m-d', $date))) {
                continue;
            }

            InvestmentPrice::updateOrCreate(
                [
                    'investment_id' => $this->id,
                    'date' => $date,
                ],
                [
                    'price' => $daily_data->{'4. close'},
                ]
            );
        }
    }
}
