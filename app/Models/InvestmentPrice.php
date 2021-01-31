<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentPrice extends Model
{
    public $timestamps = false;

    public $casts = [
        'price' => 'float',
    ];
}
