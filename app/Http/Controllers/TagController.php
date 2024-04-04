<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

class TagController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(Tag::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        /**
         * @get('/tag')
         * @name('tag.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\Tag')
         */
        // Get all tags of the user from the database and return to view
        $tags = Auth::user()
            ->tags()
            ->select('id', 'name', 'active')
            ->get()
            ->append('transaction_count');

        // Pass data for DataTables
        JavaScript::put([
            'tags' => $tags,
        ]);

        return view('tag.index');
    }

    public function create(): View
    {
        /**
         * @get('/tag/create')
         * @name('tag.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Tag')
         */
        return view('tag.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Tag  $tag
     * @return View
     */
    public function edit(Tag $tag): View
    {
        /**
         * @get('/tag/{tag}/edit')
         * @name('tag.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,tag')
         */
        return view('tag.form', ['tag' => $tag]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        /**
         * @post('/tag')
         * @name('tag.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Tag')
         */
        $request->user()->tags()->create($request->validated());

        self::addSimpleSuccessMessage(__('Tag added'));

        return redirect()->route('tag.index');
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/tag/{tag}')
         * @name('tag.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,tag')
         */
        // Retrieve the validated input data
        $validated = $request->validated();

        $tag->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Tag updated'));

        return redirect()->route('tag.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Tag  $tag
     * @return RedirectResponse
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        /**
         * @delete('/tag/{tag}')
         * @name('tag.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,tag')
         */
        $tag->delete();

        self::addSimpleSuccessMessage(__('Tag deleted'));

        return redirect()->route('tag.index');
    }
}
