<?php

namespace App\Http\View\Composers;

use App\Components\FlashMessages;
use Illuminate\View\View;

class NotificationMessageComposer
{
    use FlashMessages;

    /**
     * Bind data to the view.
     *
     * @param View $view
     */
    public function compose(View $view): void
    {
        $view->with('notifications', self::getMessages());
    }
}
