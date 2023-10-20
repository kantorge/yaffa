<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\AccountGroup
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Database\Factories\AccountGroupFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereUserId($value)
 *
 * @mixin Eloquent
 */
class AccountGroup extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
