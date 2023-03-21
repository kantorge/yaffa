<?php

namespace App\Listeners;

use App\Notifications\LoginFailedNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Notification;

class SendLoginFailedNotification
{
    /**
     * Handle the event.
     *
     * @param Failed $event
     */
    public function handle(Failed $event): void
    {
        logger()->alert('Failed login attempt', ['event' => $event]);

        if (!config('yaffa.admin_email')) {
            return;
        }

        Notification::route('mail', config('yaffa.admin_email'))->notify(new LoginFailedNotification($event));
    }
}
