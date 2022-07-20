<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\InvestmentGroup;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class Investment extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'investments';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
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
        'user_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'auto_update' => 'boolean',
    ];

    protected $appends = [
        'investment_price_provider_name',
    ];

    public function investmentPrices()
    {
        return $this->hasMany(InvestmentPrice::class);
    }

    public function investment_group()
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
                if (! is_null($account)) {
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

        //combined is needed and we have both data: get latest
        if (($price instanceof InvestmentPrice) && ($transaction instanceof Transaction)) {
            if ($price->date > $transaction->date) {
                return $price->price;
            }

            return $transaction->config->price;
        }

        //we have only stored data
        if ($price instanceof InvestmentPrice) {
            return $price->price;
        }

        //we have only transaction data
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
    protected $priceProviders = [
        'alpha_vantage' => [
            'name' => 'Alpha Vantage',
        ]
    ];

   /**
    * @return string|null
    */
    public function getInvestmentPriceProviderNameAttribute()
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
     * @return array
     */
    public function getAllInvestmentPriceProviders()
    {
        return $this->priceProviders;
    }

    /*
     * Common function to get price of investment from provider.
     * It invokes the provider's function and updates the price in the database.
     */
    public function getInvestmentPriceFromProvider(): void
    {
        $providerSuffix = 'getInvestmentPriceFrom' . str_replace([' ', '_'], '', ucwords($this->investment_price_provider_name, '_'));
        $this->{$providerSuffix}();
    }

    public function getInvestmentPriceFromAlphaVantage(): void
    {
        // Option for future: force to reload prices from provider
        $refill = false;
        // Opton for future: set earliest date to get prices from
        $from = Carbon::now()->subDays(3);

        $client = new GuzzleClient();

        $response = $client->request('GET', 'https://www.alphavantage.co/query', [
            'query' => [
                'function' => 'TIME_SERIES_DAILY',
                'datatype' => 'json',
                'symbol' => $this->symbol,
                'apikey' => config('yaffa.alpha_vantage_key'),
                'outputsize' => ($refill ? 'full' : 'compact'),
            ],
        ]);

        $obj = json_decode($response->getBody());

        foreach ($obj->{'Time Series (Daily)'} as $date => $daily_data) {
            // Option for future: if the date is before the from date, skip it
            if ($from && $from->gt(Carbon::createFromFormat('Y-m-d', $date))) {
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
