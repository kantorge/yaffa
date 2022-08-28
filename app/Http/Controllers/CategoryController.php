<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryMergeRequest;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

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
        /**
         * @get('/categories')
         * @name('categories.index')
         * @middlewares('web', 'auth', 'can:viewAny,App\Models\Category')
         */
        // Show all categories of user from the database and return to view
        $categories = Auth::user()
            ->categories()
            ->with(['parent'])
            // Also pass the number of associated standard transactions
            ->withCount([
                'transaction as transactions_count' => function (Builder $query) {
                    $query->select(DB::raw('COUNT(distinct transactions.id)'))
                        ->where('transactions.schedule', false)
                        ->where('transactions.budget', false);
                }
            ])
            // TODO: how should this be solved using withMin?
            ->withCount([
                'transaction as transactions_min_date' => function (Builder $query) {
                    $query->select(DB::raw('MIN(transactions.date)'))
                        ->where('transactions.schedule', false)
                        ->where('transactions.budget', false);
                }
            ])
            // TODO: how should this be solved using withMax?
            ->withCount([
                'transaction as transactions_max_date' => function (Builder $query) {
                    $query->select(DB::raw('MAX(transactions.date)'))
                        ->where('transactions.schedule', false)
                        ->where('transactions.budget', false);
                }
            ])
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
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
        /**
         * @get('/categories/create')
         * @name('categories.create')
         * @middlewares('web', 'auth', 'can:create,App\Models\Category')
         */
        return view('categories.form');
    }

    public function store(CategoryRequest $request)
    {
        /**
         * @post('/categories')
         * @name('categories.store')
         * @middlewares('web', 'auth', 'can:create,App\Models\Category')
         */
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
        /**
         * @get('/categories/{category}/edit')
         * @name('categories.edit')
         * @middlewares('web', 'auth', 'can:update,category')
         */
        return view(
            'categories.form',
            [
                'category' => $category,
            ]
        );
    }

    public function update(CategoryRequest $request, Category $category)
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/categories/{category}')
         * @name('categories.update')
         * @middlewares('web', 'auth', 'can:update,category')
         */
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
        /**
         * @delete('/categories/{category}')
         * @name('categories.destroy')
         * @middlewares('web', 'auth', 'can:delete,category')
         */
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
        /**
         * @get('/categories/merge/{categorySource?}')
         * @name('categories.merge.form')
         * @middlewares('web', 'auth')
         */
        if ($categorySource) {
            JavaScriptFacade::put([
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
        /**
         * @post('/categories/merge')
         * @name('categories.merge.submit')
         * @middlewares('web', 'auth')
         */
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
