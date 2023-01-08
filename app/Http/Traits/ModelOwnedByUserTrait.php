<?php

namespace App\Http\Traits;

trait ModelOwnedByUserTrait
{
    public static function bootModelOwnedByUserTrait()
    {
        if (! auth()->check()) {
            return;
        }

        static::creating(function ($model) {
            $model->user_id = auth()->id();
        });
    }
}
