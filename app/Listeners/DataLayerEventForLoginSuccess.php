<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class DataLayerEventForLoginSuccess
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // This feature is triggered only if the GTM container ID is set
        if (!config('yaffa.gtm_container_id')) {
            return;
        }

        /** @var User $user */
        $user = $event->user;

        // As this event happens on the server side, we need to push the event to the dataLayer of the next page load
        $dataLayer = session()->get('dataLayer', []);
        $dataLayer[] = [
            'event' => 'loginSuccess',
            'is_generic_demo_user' => $user->email === 'demo@yaffa.cc',
        ];
        session()->flash('dataLayer', $dataLayer);
    }
}
