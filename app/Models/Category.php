<?php

namespace App\Models;

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionItem;
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

    /**
     * Scope a query to only include top level categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionItem()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function transaction()
    {
        return $this->hasManyThrough(Transaction::class, TransactionItem::class, 'category_id', 'id', 'id', 'transaction_id');
    }

    public function payeesNotPreferring()
    {
        return $this->belongsToMany(AccountEntity::class, 'account_entity_category_preference')->where('preferred', false);
    }
}
