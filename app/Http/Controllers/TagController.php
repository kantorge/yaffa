<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use JavaScript;

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
        // Get all tags of the user from the database and return to view
        $tags = Auth::user()
            ->tags()
            ->select('id', 'name', 'active')
            ->get();

        // Pass data for DataTables
        JavaScript::put([
            'tags' => $tags,
        ]);

        return view('tag.index');
    }

    public function create()
    {
        return view('tag.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Tag $tag
     * @return \Illuminate\View\View
     */
    public function edit(Tag $tag)
    {
        return view('tag.form', ['tag'=> $tag]);
    }

    public function store(TagRequest $request)
    {
        $validated = $request->validated();

        $tag = Tag::make($validated);
        $tag->user_id = Auth::user()->id;
        $tag->save();

        self::addSimpleSuccessMessage('Tag added');

        return redirect()->route('tag.index');
    }

    public function update(TagRequest $request, Tag $tag)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $tag->fill($validated)
            ->save();

        self::addSimpleSuccessMessage('Tag updated');

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
        $tag->delete();

        self::addSimpleSuccessMessage('Tag deleted');

        return redirect()->route('tag.index');
    }
}
