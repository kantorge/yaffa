<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{

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
        'auto_update'
    ];

    protected $casts = [
        'base' => 'boolean',
        'auto_update' => 'boolean',
    ];

    public static function rules ($id = 0) {
        return
            [
                'name' => 'required|min:2|max:191|unique:currencies,name' . ($id ? ",$id" : ''),
                'iso_code' => 'required|string|size:3|unique:currencies,iso_code' . ($id ? ",$id" : ''),
                'num_digits' => 'required|numeric|between:0,4',
                'suffix' => 'string|nullable|max:5',
                'base' => 'boolean|nullable|unique:currencies,base' . ($id ? ",$id" : ''),
                'auto_update' => 'boolean'
            ];
    }

}