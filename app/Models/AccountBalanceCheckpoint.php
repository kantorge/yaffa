<?php

namespace App\Models;

use Database\Factories\AccountBalanceCheckpointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $account_entity_id
 * @property \Illuminate\Support\Carbon $checkpoint_date
 * @property string $checkpoint_type
 * @property float $balance
 * @property string|null $note
 * @property bool $active
 * @property string $source
 * @property string|null $source_document_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AccountEntity $accountEntity
 * @property-read User $user
 * @method static AccountBalanceCheckpointFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class AccountBalanceCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_entity_id',
        'checkpoint_date',
        'checkpoint_type',
        'balance',
        'note',
        'active',
        'source',
        'source_document_id',
    ];

    protected function casts(): array
    {
        return [
            'checkpoint_date' => 'date',
            'balance' => 'float',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountEntity(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class);
    }
}
