<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Links a transaction to the source it came from (`created`) or to a document that matched it
 * (`duplicate_of`, `conflicts_with`). See the fast transaction entry specification.
 *
 * @property int $id
 * @property int $user_id
 * @property int $transaction_id
 * @property string|null $origin_type
 * @property int|null $origin_id
 * @property string $relation
 * @property string|null $decision_reason
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Transaction $transaction
 * @property-read Model|null $origin
 */
#[Fillable('transaction_id', 'origin_type', 'origin_id', 'relation', 'decision_reason', 'reviewed_at')]
class TransactionOrigin extends Model
{
    use ModelOwnedByUserTrait;

    public const string RELATION_CREATED = 'created';

    public const string RELATION_DUPLICATE_OF = 'duplicate_of';

    public const string RELATION_CONFLICTS_WITH = 'conflicts_with';

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * `user_id` is not mass assignable, so origins are always written through here.
     */
    public static function record(User $user, Transaction $transaction, string $relation, ?Model $origin, ?string $reason = null): self
    {
        $record = new self([
            'transaction_id' => $transaction->id,
            'relation' => $relation,
            'decision_reason' => $reason,
        ]);
        $record->user_id = $user->id;

        if ($origin !== null) {
            $record->origin()->associate($origin);
        }

        $record->save();

        return $record;
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }
}
