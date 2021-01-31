<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\CategoryRequest;
use Illuminate\Http\Request;
use JavaScript;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all categories from the database and return to view
        $categories = Category::with(['parent'])->get();

        //pass data for DataTables
        JavaScript::put([
            'categories' => $categories,
            'editUrl' => route('categories.edit', '#ID#'),
            'deleteUrl' => action('CategoryController@destroy', '#ID#'),
        ]);

        return view('categories.index');
    }

    public function create()
    {
        //get all possible parents
        $parents = Category::whereNull('parent_id')->pluck('name', 'id');

        return view('categories.form', ['parents' => $parents]);
    }

    public function store(CategoryRequest $request)
    {

        $validated = $request->validated();

        $category = New Category();
        $category->fill($validated);
        $category->save();

        add_notification('Category added', 'success');

        return redirect()->route('categories.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $category = Category::find($id);

        //get all possible parents
        $parents = Category::whereNull('parent_id')->where('id', '!=',  $id)->pluck('name', 'id');

        return view('categories.form',['category'=> $category, 'parents' => $parents]);
    }

    public function update(CategoryRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $category = Category::find($request->input('id'));
        $category->fill($validated);
        $category->save();

        add_notification('Category updated', 'success');

        return redirect()->route('categories.index');
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
        $category = Category::find($id);

        //delete
        $category->delete();

        add_notification('Category deleted', 'success');

        return redirect()->route('categories.index');
    }

}
