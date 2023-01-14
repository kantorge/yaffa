<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\AccountGroup
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AccountGroupFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereUserId($value)
 * @mixin \Eloquent
 */
class AccountGroup extends Model
{
    use HasFactory, ModelOwnedByUserTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
