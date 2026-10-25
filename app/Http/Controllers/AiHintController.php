<?php

namespace App\Http\Controllers;

use App\Models\AiHint;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class AiHintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:viewAny,' . AiHint::class, only: ['index']),
        ];
    }

    public function index(): View
    {
        /**
         * @get("/ai-hints")
         * @name("ai-hints.index")
         * @middlewares("web", "auth", "verified")
         */
        return view('ai-hints.index');
    }
}
