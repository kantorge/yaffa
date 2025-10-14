<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use App\Http\Traits\ModelOwnedByUserTrait;
use App\Spiders\InvestmentPriceScraper;
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
use Illuminate\Support\Facades\DB;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

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

    public function getCurrentQuantity(AccountEntity $account = null): float
    {
        $quantity = DB::table('transactions')
            ->select(
                DB::raw('sum(
                                  IFNULL(transaction_types.quantity_multiplier, 0)
                                  * IFNULL(transaction_details_investment.quantity, 0)
                                ) AS quantity')
            )
            ->leftJoin(
                'transaction_types',
                'transactions.transaction_type_id',
                '=',
                'transaction_types.id'
            )
            ->leftJoin(
                'transaction_details_investment',
                'transactions.config_id',
                '=',
                'transaction_details_investment.id'
            )
            ->where('transactions.schedule', 0)
            ->where('transactions.config_type', 'investment')
            ->where('transaction_details_investment.investment_id', $this->id)
            ->when($account !== null, function ($query) use ($account) {
                $query->where('transaction_details_investment.account_id', '=', $account->id);
            })
            ->get();

        return $quantity->first()->quantity ?? 0;
    }

    /**
     * Get the latest price of the investment
     *
     * @param string $type Can be 'stored', 'transaction' or 'combined'
     */
    public function getLatestPrice(string $type = 'combined', Carbon $onOrBefore = null): ?float
    {
        if ($type === 'stored') {
            $price = $this->getLatestStoredPrice($onOrBefore);
            return $price instanceof InvestmentPrice ? $price->price : null;
        }

        if ($type === 'transaction') {
            $transaction = $this->getLatestTransactionWithPrice($onOrBefore);
            return $transaction instanceof Transaction ? $transaction->config->price : null;
        }

        // Proceed with combined price
        return $this->getLatestCombinedPrice($onOrBefore);
    }

    private function getLatestStoredPrice(Carbon $onOrBefore = null)
    {
        return InvestmentPrice::where('investment_id', $this->id)
            ->when($onOrBefore, function ($query) use ($onOrBefore) {
                $query->where('date', '<=', $onOrBefore);
            })
            ->latest('date')
            ->first();
    }

    private function getLatestTransactionWithPrice(Carbon $onOrBefore = null)
    {
        return Transaction::with([
            'config',
        ])
            ->byScheduleType('none')
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) {
                    $query
                        ->where('investment_id', $this->id)
                        ->whereNotNull('price');
                }
            )
            ->when($onOrBefore, function ($query) use ($onOrBefore) {
                $query->where('date', '<=', $onOrBefore);
            })
            ->latest('date')
            ->first();
    }

    private function getLatestCombinedPrice(Carbon $onOrBefore = null)
    {
        $price = $this->getLatestStoredPrice($onOrBefore);
        $transaction = $this->getLatestTransactionWithPrice($onOrBefore);

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

    /**
     * @var array
     */
    protected array $priceProviders = [
        'alpha_vantage' => [
            'name' => 'Alpha Vantage',
            'refillAvailable' => true,
            'description' => 'Alpha Vantage is a leading provider of free APIs for historical and real-time data on stocks, forex (FX), and digital/crypto currencies.',
            'instructions' => 'To use Alpha Vantage, you need to get an API key. The key is free, but you need to register on their website.',
        ],
        'web_scraping' => [
            'name' => 'Web Scraping',
            'refillAvailable' => false,
            'description' => 'Web scraping is a technique to extract data from websites. It is a common method to get data from websites that do not provide APIs.',
            'instructions' => 'To use web scraping, you need to provide a URL and a CSS selector to extract the price from the website.',
        ],
    ];

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
        if (!$this->investment_price_provider) {
            return null;
        }

        $provider = Arr::get($this->priceProviders, $this->investment_price_provider);

        if ($provider !== null) {
            return $provider['name'];
        }

        return null;
    }

    /**
     * Return all available price providers with all their details.
     */
    public function getAllInvestmentPriceProviders(): array
    {
        return $this->priceProviders;
    }

    /**
     * Common function to get price of investment from provider.
     * It invokes the provider's function and updates the price in the database.
     *
     * @param Carbon|null $from Optionally specify the date to retrieve data from
     * @param bool $refill Future option to request reload of prices
     * @uses getInvestmentPriceFromAlphaVantage()
     * @uses getInvestmentPriceFromWebScraping()
     */
    public function getInvestmentPriceFromProvider(Carbon $from = null, bool $refill = false): void
    {
        $providerSuffix = 'getInvestmentPriceFrom' . str_replace([' ', '_'], '', ucwords($this->investment_price_provider_name, '_'));
        $this->{$providerSuffix}($from, $refill);
    }

    /**
     * TODO: this should have a contract to have standard parameters, and to force the silent save
     * TODO: this is getting and saving the data, but it should be split into two functions
     * @param Carbon|null $from Optionally specify the date to retrieve data from
     * @param bool $refill Future option to request reload of prices
     * @throws GuzzleException
     */
    public function getInvestmentPriceFromAlphaVantage(Carbon|null $from = null, bool $refill = false): void
    {
        // Get 3 days data by default, assuming that scheduler is running
        if (!$from) {
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

            $investmentPrice = InvestmentPrice::firstOrNew(
                [
                    'investment_id' => $this->id,
                    'date' => $date,
                ]
            );
            $investmentPrice->price = $daily_data->{'4. close'};

            // We are intentionally not triggering the observer here, as there can be multiple similar operations
            // It means, that it's the responsibility of the caller to trigger the observer or any related actions
            $investmentPrice->saveQuietly();
        }
    }

    /**
     * TODO: this should have a contract to have standard parameters, and to force the silent save
     * TODO: this is getting and saving the data, but it should be split into two functions
     * @param Carbon|null $from Optionally specify the date to retrieve data from
     * @param bool $refill Future option to request reload of prices
     */
    public function getInvestmentPriceFromWebScraping(Carbon|null $from = null, bool $refill = false): void
    {
        // This provider ignores the $from and $refill parameters, as it looks for the latest price,
        // which is assumed to be the one applying to the previous day

        $result = Roach::collectSpider(
            InvestmentPriceScraper::class,
            new Overrides(
                startUrls: [$this->scrape_url],
            ),
            [
                'selector' => $this->scrape_selector,
            ]
        );

        // TODO: proper error handling
        if (sizeof($result) === 0) {
            return;
        }

        $investmentPrice = InvestmentPrice::firstOrNew(
            [
                'investment_id' => $this->id,
                'date' => Carbon::yesterday()->format('Y-m-d'),
            ]
        );

        $investmentPrice->price = $result[0]->get('price');

        // We are intentionally not triggering the observer here, as there can be multiple similar operations
        // It means, that it's the responsibility of the caller to trigger the observer or any related actions
        $investmentPrice->saveQuietly();
    }
}
