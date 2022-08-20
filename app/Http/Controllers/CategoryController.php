<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryMergeRequest;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * @param  Category  $category
     * @return \Illuminate\View\View
     */
    public function edit(Category $category)
    {
        return view(
            'categories.form',
            [
                'category' => $category,
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
     * @param  Category  $category
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

    /**
     * Display a form to merge two categories.
     *
     * @param  \App\Models\Category  $categorySource
     * @return \Illuminate\Http\Response
     */
    public function mergeCategoriesForm(?Category $categorySource)
    {
        if ($categorySource) {
            JavaScript::put([
                'categorySource' => $categorySource->toArray(),
            ]);
        }

        return view('categories.merge');
    }

    /*
     * Merge two categories.
     */
    public function mergeCategories(CategoryMergeRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        // Wrap database transaction
        DB::beginTransaction();
        try {
            // Update all transaction detail items with source category to target category
            DB::table('transaction_items')
            ->where('category_id', $validated['category_source'])
            ->update(['category_id' => $validated['category_target']]);

            // Update all child categories with source parent to target parent
            DB::table('categories')
            ->where('parent_id', $validated['category_source'])
            ->update(['parent_id' => $validated['category_target']]);

            // Hydrate the source category
            $categorySource = Category::find($validated['category_source']);

            // Delete or set active to false the source category model, based on value of action field
            if ($request->action === 'delete') {
                $categorySource->delete();
            } else {
                $categorySource->active = false;
                $categorySource->push();
            }

            DB::commit();
            self::addSimpleSuccessMessage('Categories merged');
        } catch (\Exception $e) {
            DB::rollback();
            self::addSimpleDangerMessage('Database error: '.$e->getMessage());
        }

        return redirect()->route('categories.index');
    }
}
