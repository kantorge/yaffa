<?php

namespace App\Models;

use App\Observers\InvestmentPriceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\InvestmentPrice
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $investment_id
 * @property float $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Investment $investment
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice query()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereInvestmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereUpdatedAt($value)
 * @mixin \Eloquent
 */
#[ObservedBy([InvestmentPriceObserver::class])]
class InvestmentPrice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'investment_prices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'investment_id',
        'price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'datetime:Y-m-d',
            'price' => 'float',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
