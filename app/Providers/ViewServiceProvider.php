<?php

namespace App\Providers;

use App\Http\View\Composers\AccountListComposer;
use App\Http\View\Composers\NotificationMessageComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('*', AccountListComposer::class);
        View::composer('*', NotificationMessageComposer::class);
    }
}
