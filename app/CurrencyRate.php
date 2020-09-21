<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LabelsTrait;

class CurrencyRate extends Model
{

    use LabelsTrait;

    protected $table = 'currency_rates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'from_id',
        'to_id',
        'rate',
    ];

    public static $labels = [
    ];
}