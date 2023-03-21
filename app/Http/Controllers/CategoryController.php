<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryMergeRequest;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Exception;
use Throwable;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(Category::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        /**
         * @get('/categories')
         * @name('categories.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\Category')
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
     * @return View
     */
    public function create()
    {
        /**
         * @get('/categories/create')
         * @name('categories.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Category')
         */
        return view('categories.form');
    }

    public function store(CategoryRequest $request)
    {
        /**
         * @post('/categories')
         * @name('categories.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Category')
         */
        Category::create($request->validated());

        self::addSimpleSuccessMessage(__('Category added'));

        return redirect()->route('categories.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Category  $category
     * @return View
     */
    public function edit(Category $category)
    {
        /**
         * @get('/categories/{category}/edit')
         * @name('categories.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,category')
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
         * @middlewares('web', 'auth', 'verified', 'can:update,category')
         */
        // Retrieve the validated input data
        $validated = $request->validated();

        $category->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Category updated'));

        return redirect()->route('categories.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Category  $category
     * @return Response
     */
    public function destroy(Category $category)
    {
        /**
         * @delete('/categories/{category}')
         * @name('categories.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,category')
         */
        try {
            $category->delete();
            self::addSimpleSuccessMessage(__('Category deleted'));

            return redirect()->route('categories.index');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                self::addSimpleDangerMessage(__('Category is in use, cannot be deleted'));
            } else {
                self::addSimpleDangerMessage(__('Database error:') . ' ' . $e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }

    /**
     * Display a form to merge two categories.
     *
     * @param Category $categorySource
     * @return View
     */
    public function mergeCategoriesForm(?Category $categorySource)
    {
        /**
         * @get('/categories/merge/{categorySource?}')
         * @name('categories.merge.form')
         * @middlewares('web', 'auth', 'verified')
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
    /**
     * @throws Throwable
     */
    public function mergeCategories(CategoryMergeRequest $request): RedirectResponse
    {
        /**
         * @post('/categories/merge')
         * @name('categories.merge.submit')
         * @middlewares('web', 'auth', 'verified')
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
            self::addSimpleSuccessMessage(__('Categories merged'));
        } catch (Exception $e) {
            DB::rollback();
            self::addSimpleDangerMessage(__('Database error:') . ' ' . $e->getMessage());
        }

        return redirect()->route('categories.index');
    }
}
