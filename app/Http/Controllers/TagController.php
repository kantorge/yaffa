<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class TagController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Tag::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        /**
         * @get('/tag')
         * @name('tag.index')
         * @middlewares('web', 'auth', 'can:viewAny,App\Models\Tag')
         */
        // Get all tags of the user from the database and return to view
        $tags = Auth::user()
            ->tags()
            ->select('id', 'name', 'active')
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
            'tags' => $tags,
        ]);

        return view('tag.index');
    }

    public function create()
    {
        /**
         * @get('/tag/create')
         * @name('tag.create')
         * @middlewares('web', 'auth', 'can:create,App\Models\Tag')
         */
        return view('tag.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Tag  $tag
     * @return \Illuminate\View\View
     */
    public function edit(Tag $tag)
    {
        /**
         * @get('/tag/{tag}/edit')
         * @name('tag.edit')
         * @middlewares('web', 'auth', 'can:update,tag')
         */
        return view('tag.form', ['tag' => $tag]);
    }

    public function store(TagRequest $request)
    {
        /**
         * @post('/tag')
         * @name('tag.store')
         * @middlewares('web', 'auth', 'can:create,App\Models\Tag')
         */
        $validated = $request->validated();

        $tag = Tag::make($validated);
        $tag->user_id = Auth::user()->id;
        $tag->save();

        self::addSimpleSuccessMessage(__('Tag added'));

        return redirect()->route('tag.index');
    }

    public function update(TagRequest $request, Tag $tag)
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/tag/{tag}')
         * @name('tag.update')
         * @middlewares('web', 'auth', 'can:update,tag')
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
     * @return \Illuminate\Http\Response
     */
    public function destroy(Tag $tag)
    {
        /**
         * @delete('/tag/{tag}')
         * @name('tag.destroy')
         * @middlewares('web', 'auth', 'can:delete,tag')
         */
        $tag->delete();

        self::addSimpleSuccessMessage(__('Tag deleted'));

        return redirect()->route('tag.index');
    }
}
