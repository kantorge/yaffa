<?php

namespace App\Http\Traits;

/**
 * Defaults a model's user_id to the authenticated user on create.
 *
 * Only fills user_id when it isn't already set — never overwrites an explicit
 * value (factory ->for($user), an admin/service assigning a specific owner,
 * etc.). Same shape as Illuminate\Database\Eloquent\Concerns\HasUniqueIds::setUniqueIds().
 *
 * This is convenience, not the security boundary: it must never be relied on
 * to sanitize a client-supplied user_id. That protection belongs to each
 * model's $fillable/$guarded — keep user_id out of $fillable unless the
 * caller explicitly (and safely) sets it server-side, as
 * CategoryLearningService does.
 */
trait ModelOwnedByUserTrait
{
    public static function bootModelOwnedByUserTrait(): void
    {
        if (! auth()->check()) {
            return;
        }

        static::creating(function ($model) {
            // Only default to the authenticated user - never override a user_id the
            // caller already set explicitly (e.g. via a relation like $user->currencies()
            // ->create(...), or a factory building data for a specific, non-acting user).
            if ($model->user_id === null) {
                $model->user_id = auth()->id();
            }
        });
    }
}
