<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class TagController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:viewAny,' . Tag::class, only: ['index']),
            new Middleware('can:create,' . Tag::class, only: ['create', 'store']),
            new Middleware('can:update,tag', only: ['edit', 'update']),
            new Middleware('can:delete,tag', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        /**
         * @get("/tags")
         * @name("tags.index")
         * @middlewares("web", "auth", "verified")
         */
        // Get all tags of the user from the database and return to view
        $tags = $request->user()
            ->tags()
            ->select('id', 'name', 'active')
            ->get()
            ->append('transaction_count');

        // Pass data for DataTables
        JavaScript::put([
            'tags' => $tags,
        ]);

        return view('tags.index');
    }

    public function create(): View
    {
        /**
         * @get("/tags/create")
         * @name("tags.create")
         * @middlewares("web", "auth", "verified")
         */
        return view('tags.form');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag): View
    {
        /**
         * @get("/tags/{tag}/edit")
         * @name("tags.edit")
         * @middlewares("web", "auth", "verified")
         */
        return view('tags.form', ['tag' => $tag]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        /**
         * @post("/tags")
         * @name("tags.store")
         * @middlewares("web", "auth", "verified")
         */
        $request->user()->tags()->create($request->validated());

        self::addSimpleSuccessMessage(__('Tag added'));

        return to_route('tags.index');
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        /**
         * @methods("PUT", "PATCH")
         * @uri("/tags/{tag}")
         * @name("tags.update")
         * @middlewares("web", "auth", "verified")
         */
        // Retrieve the validated input data
        $validated = $request->validated();

        $tag->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Tag updated'));

        return to_route('tags.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        /**
         * @delete("/tags/{tag}")
         * @name("tags.destroy")
         * @middlewares("web", "auth", "verified")
         */
        $tag->delete();

        self::addSimpleSuccessMessage(__('Tag deleted'));

        return to_route('tags.index');
    }
}
