<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountEntity extends Model
{
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

    public function config()
    {
        return $this->morphTo();
    }

}
