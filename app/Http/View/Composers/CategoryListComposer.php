<?php

namespace App\Http\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CategoryListComposer
{
    /**
     * Bind list of all accounts to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        // Get all categories
        $categories = Auth::user()
            ->categories()
            ->get()
            ->sortBy('full_name')
            ->pluck('full_name', 'id');

        $view->with('categories', $categories);
    }
}
