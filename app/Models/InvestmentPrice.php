<?php

namespace App\Models;

use App\Models\Investment;
use Illuminate\Database\Eloquent\Model;

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
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'datetime:Y-m-d',
        'price' => 'float',
    ];

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }
}
