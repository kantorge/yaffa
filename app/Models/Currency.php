<?php

namespace App\Models;

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Currency extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'currencies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'iso_code',
        'num_digits',
        'suffix',
        'base',
        'auto_update',
        'user_id',
    ];

    protected $casts = [
        'base' => 'boolean',
        'auto_update' => 'boolean',
    ];

    public function rate()
    {
        $baseCurrency = self::where('base', 1)->firstOr(function () {
            return self::orderBy('id')->firstOr(function () {
                return null;
            });
        });

        $rate = CurrencyRate::where('from_id', $this->id)
                                    ->where('to_id', $baseCurrency->id)
                                    ->latest('date')
                                    ->first();

        return $rate instanceof CurrencyRate ? $rate->rate : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
