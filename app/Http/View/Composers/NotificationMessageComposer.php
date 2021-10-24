<?php

namespace App\Http\View\Composers;

use App\Components\FlashMessages;
use Illuminate\View\View;

class NotificationMessageComposer
{
    /**
     * Bind data to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('notifications', FlashMessages::getMessages());
    }
}
