<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Models\Tag;
use JavaScript;

class TagController extends Controller
{
    protected $tag;

    public function __construct(Tag $tag)
    {
        $this->tag = $tag;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        //Show all tags from the database and return to view
        $tags = $this->tag->all();

        //pass data for DataTables
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

        Tag::create($validated);

        self::addSimpleSuccessMessage('Tag added');

        return redirect()->route('tag.index');
    }

    public function update(TagRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        Tag::find($request->input('id'))
            ->fill($validated)
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
