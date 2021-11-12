<?php

namespace App\Listeners;

use App\Notifications\LoginSuccessNotification;
use Illuminate\Support\Facades\Notification;

class SendLoginSuccessNotification
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if (config('yaffa.admin_email')) {
            Notification::route('mail', config('yaffa.admin_email'))->notify(new LoginSuccessNotification($event));
        }
    }
}
