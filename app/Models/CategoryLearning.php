<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryLearning extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected $table = 'category_learning';

    protected $fillable = [
        'item_description',
        'category_id',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
