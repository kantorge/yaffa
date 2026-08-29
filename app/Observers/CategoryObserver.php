<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CategoryWaterfallCacheService;

class CategoryObserver
{
    /**
     * Handle the Category "created" event.
     */
    public function created(Category $category): void
    {
        CategoryWaterfallCacheService::forgetAllForUser($category->user_id);
    }

    /**
     * Handle the Category "updated" event.
     */
    public function updated(Category $category): void
    {
        CategoryWaterfallCacheService::forgetAllForUser($category->user_id);
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        CategoryWaterfallCacheService::forgetAllForUser($category->user_id);
    }
}
