<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use JavaScript;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Category::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Show all categories of user from the database and return to view
        $categories = Auth::user()
            ->categories()
            ->with(['parent'])
            ->get();

        // Pass data for DataTables
        JavaScript::put([
            'categories' => $categories,
        ]);

        return view('categories.index');
    }

    /**
     * Display a form for adding new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('categories.form');
    }

    public function store(CategoryRequest $request)
    {
        $validated = $request->validated();

        $category = Category::make($validated);
        $category->user_id = Auth::user()->id;
        $category->save();

        self::addSimpleSuccessMessage('Category added');

        return redirect()->route('categories.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Category $category
     * @return \Illuminate\View\View
     */
    public function edit(Category $category)
    {
        return view(
            'categories.form',
            [
                'category'=> $category,
            ]
        );
    }

    public function update(CategoryRequest $request, Category $category)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $category->fill($validated)
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
        try {
            $category->delete();
            self::addSimpleSuccessMessage('Category deleted');

            return redirect()->route('categories.index');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Category is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: '.$e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }
}
