<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

class DataLayerEventForLoginFailed
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        // This feature is triggered only if the GTM container ID is set
        if (!config('yaffa.gtm_container_id')) {
            return;
        }

        // As this event happens on the server side, we need to push the event to the dataLayer of the next page load
        $dataLayer = session()->get('dataLayer', []);
        $dataLayer[] = [
            'event' => 'loginFailed',
            'is_generic_demo_user' => data_get($event, 'credentials.email') === 'demo@yaffa.cc',
        ];
        session()->flash('dataLayer', $dataLayer);
    }
}
