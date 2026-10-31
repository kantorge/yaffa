<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $route
 * @property string $request_hash
 * @property int|null $status_code
 * @property string|null $response_body
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class IdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'key',
        'route',
        'request_hash',
        'status_code',
        'response_body',
    ];
}
