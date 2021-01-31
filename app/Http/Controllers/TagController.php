<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Http\Requests\TagRequest;
use Illuminate\Http\Request;
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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all account groups from the database and return to view
        $tags = $this->tag->all();

        //pass data for DataTables
        JavaScript::put([
            'tags' => $tags,
            'editUrl' => route('tags.edit', '#ID#'),
            'deleteUrl' => action('TagController@destroy', '#ID#'),
        ]);

        return view('tags.index');
    }

    public function create()
    {
        return view('tags.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $tag = Tag::find($id);

        return view('tags.form',['tag'=> $tag]);
    }

    public function store(TagRequest $request)
    {

        $validated = $request->validated();

        $tag = New Tag();
        $tag->fill($validated);
        $tag->save();

        add_notification('Tag added', 'success');

        return redirect()->route('tags.index');
    }

    public function update(TagRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $tag = Tag::find($request->input('id'));
        $tag->fill($validated);
        $tag->save();

        add_notification('Tag updated', 'success');

        return redirect()->route('tags.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Retrieve item
        $tag = Tag::find($id);
        //delete
        $tag->delete();

        add_notification('Tag deleted', 'success');

        return redirect()->route('tags.index');
    }

}
