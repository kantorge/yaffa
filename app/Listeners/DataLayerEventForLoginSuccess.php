<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DataLayerEventForLoginSuccess
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // This feature is triggered only if the GTM container ID is set
        if (! config('yaffa.gtm_container_id')) {
            return;
        }

        // As this event happens on the server side, we need to push the event to the dataLayer of the next page load
        $dataLayer = session()->get('dataLayer', []);
        $dataLayer[] = [
            'event' => 'loginSuccess',
            'user' => hash('sha256', $event->user->email),
        ];
        session()->flash('dataLayer', $dataLayer);
    }
}
