<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestmentGroup extends Model
{

    protected $table = 'investment_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    public static function rules ($id = 0) {
        return
            [
                'name' => 'required|min:2|max:191|unique:investment_groups,name' . ($id ? ",$id" : ''),
            ];
    }

}
