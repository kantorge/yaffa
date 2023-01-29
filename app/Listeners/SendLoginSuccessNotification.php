<?php

namespace App\Listeners;

use App\Notifications\LoginSuccessNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Notification;

class SendLoginSuccessNotification
{
    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event): void
    {
        if (! config('yaffa.admin_email')) {
            return;
        }

        Notification::route('mail', config('yaffa.admin_email'))->notify(new LoginSuccessNotification($event));
    }
}
