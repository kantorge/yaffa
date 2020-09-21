<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\Rule;

class Payee extends AccountEntity
{
    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payees';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'categories_id',
    ];

    public static $labels = [
        'category' => 'Category',
    ];

    public function config()
    {
        return $this->morphOne(AccountEntity::class, 'config');
    }

    public function categories()
    {
        return $this->belongsTo(Category::class);
    }
}
