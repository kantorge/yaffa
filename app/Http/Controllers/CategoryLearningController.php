<?php

namespace App\Http\Controllers;

use App\Models\CategoryLearning;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CategoryLearningController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:viewAny,' . CategoryLearning::class, only: ['index']),
        ];
    }

    public function index(): View
    {
        /**
         * @get("/category-learning")
         * @name("category-learning.index")
         * @middlewares("web", "auth", "verified")
         */
        return view('category-learning.index');
    }
}
