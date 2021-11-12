<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $with = [
        'parent',
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
        'user_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = [
        'full_name',
    ];

    public static function rules()
    {
        return [
            'name' => 'required|min:2|max:191',
            'active' => 'boolean',
            'parent_id' => 'in:category,id',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class);
    }

    public function getFullNameAttribute()
    {
        return (isset($this->parent->name) ? $this->parent->name.' > ' : '').$this['name'];
    }

    /**
     * Scope a query to only include active entities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
