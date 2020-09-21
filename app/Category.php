<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\LabelsTrait;

class Category extends Model
{

    use LabelsTrait;

    protected $table = 'categories';

    protected $with = [
        'parent'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'active',
        'parent_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = [
        'full_name'
    ];

    public static function rules ($id = 0) {
        return
            [
                'name' => 'required|min:2|max:191',
                'active' => 'boolean',
                'parent_id' => 'in:category,id',
            ];
    }

    public static $labels = [
        'id' => 'ID',
        'name' => 'Category',
        'parent' => 'Kategória csoport',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class);
    }

    public function getFullNameAttribute() {
        return (isset($this->parent->name) ? $this->parent->name . " > " : "") . $this['name'];
    }
}