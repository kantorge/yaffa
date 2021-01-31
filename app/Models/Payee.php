<?php

namespace App\Models;

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
        'category_id',
    ];

    public function config()
    {
        return $this->morphOne(AccountEntity::class, 'config');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
