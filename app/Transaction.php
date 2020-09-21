<?php

namespace App;

use App\Traits\LabelsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Transaction extends Model
{
    use LabelsTrait;

    protected $table = 'transaction_headers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'transaction_types',
        'reconciled',
        'is_schedule',
        'is_budget',
        'comment',
        'config_type',
        'config_id'
    ];

    protected $hidden = ['config_id'];

    //protected $with = ['config'];

    protected $casts = [
        'active' => 'reconciled',
        'active' => 'is_schedule',
        'active' => 'is_budget',
    ];

    public static $labels = [
        'id' => 'ID',
    ];

    public function config()
    {
        return $this->morphTo();
    }

}
