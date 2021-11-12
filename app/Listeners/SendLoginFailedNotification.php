<?php

namespace App\Listeners;

use App\Notifications\LoginFailedNotification;
use Illuminate\Support\Facades\Notification;

class SendLoginFailedNotification
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        logger()->alert('Failed login attempt', ['event' => $event]);

        if (config('yaffa.admin_email')) {
            Notification::route('mail', config('yaffa.admin_email'))->notify(new LoginFailedNotification($event));
        }
    }
}
