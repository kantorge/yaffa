<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Database\Factories\TransactionTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reusable, partial transaction (a schema v2 draft without a date), see
 * .ai/docs/specifications/fast-transaction-entry/specification.md.
 *
 * `payee_id` is derived from the draft by the controller and is deliberately not fillable.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int|null $payee_id
 * @property array $draft
 * @property bool $is_featured
 * @property int $use_count
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read AccountEntity|null $payee
 * @method static TransactionTemplateFactory factory(...$parameters)
 */
#[Fillable('name', 'draft', 'is_featured')]
class TransactionTemplate extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'is_featured' => 'boolean',
            'use_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class, 'payee_id');
    }

    /**
     * Count one use. A single atomic UPDATE, so concurrent saves are not lost.
     */
    public function recordUse(): void
    {
        $this->newQuery()->whereKey($this->getKey())->incrementEach(['use_count' => 1], ['last_used_at' => now()]);
    }
}
