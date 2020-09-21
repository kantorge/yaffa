<?php

namespace App;

use App\Traits\LabelsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AccountEntity extends Model
{
    use LabelsTrait;

    protected $table = 'account_entities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'active',
        'config_type',
        'config_id'
    ];

    protected $hidden = ['config_id'];

    //protected $with = ['config'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public static $labels = [
        'id' => 'ID',
        'name' => 'Name',
        'active' => 'Active',
    ];

    public function config()
    {
        return $this->morphTo();
    }

}
