<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $personal_access_token_id
 * @property string $type unifiedpush|fcm
 * @property string $endpoint
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[Fillable('user_id', 'personal_access_token_id', 'type', 'endpoint')]
class Device extends Model
{
}
