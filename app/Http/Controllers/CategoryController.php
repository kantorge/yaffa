<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
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

        Category::create($validated);

        self::addSimpleSuccessMessage('Category added');

        return redirect()->route('categories.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Category $category
     * @return \Illuminate\Http\Response
     */
    public function edit(Category $category)
    {
        //get all possible parents
        $parents = Category::
            whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->pluck('name', 'id');

        return view(
            'categories.form',
            [
                'category'=> $category,
                'parents' => $parents,
            ]
        );
    }

    public function update(CategoryRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        Category::find($request->input('id'))
            ->fill($validated)
            ->save();

            self::addSimpleSuccessMessage('Category updated');

        return redirect()->route('categories.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Category $category
     * @return \Illuminate\Http\Response
     */
    public function destroy(Category $category)
    {
        //delete
        $category->delete();

        self::addSimpleSuccessMessage('Category deleted');

        return redirect()->route('categories.index');
    }
}
