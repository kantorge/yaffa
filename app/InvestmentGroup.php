<?php

namespace App;

use App\Traits\LabelsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvestmentGroup extends Model
{
    use LabelsTrait;

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

    public static $labels = [
        'id' => 'ID',
        'name' => 'Investment group'
    ];

}
