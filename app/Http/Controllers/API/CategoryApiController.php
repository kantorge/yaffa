<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CategoryApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getList(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/category')
         * @middlewares('api', 'auth:sanctum')
         */
        $user = $request->user();

        $query = $request->get('q');
        if ($query && $query !== '*') {
            $categories = $user->categories()
                ->when($request->missing('withInactive'), function ($query) {
                    $query->active();
                })
                // Exclude not preferred categories even when searching for them
                ->when($request->has('payee'), function ($query) use ($request) {
                    $query->whereDoesntHave(
                        'payeesNotPreferring',
                        function (Builder $query) use ($request) {
                            $query->where('account_entity_id', $request->get('payee'))->where('preferred', false);
                        }
                    )->get();
                })
                ->get()
                ->filter(fn ($category) => mb_stripos($category->full_name, $query) !== false)
                ->sortBy('full_name')
                ->take(10)
                ->map(function ($category) {
                    $category->text = $category->full_name;

                    return $category->only(['id', 'text']);
                })
                ->values();
        } elseif ($query === '*') {
            $categories = $user->categories()
                ->when($request->missing('withInactive'), function ($query) {
                    $query->active();
                })
                ->get()
                ->sortBy('full_name')
                ->map(function ($category) {
                    $category->text = $category->full_name;

                    return $category->only(['id', 'text']);
                })
                ->values();
        } else {
            $results = DB::table('transaction_items')
                ->join(
                    'transactions',
                    'transactions.id',
                    '=',
                    'transaction_items.transaction_id'
                )
                ->join(
                    'transaction_details_standard',
                    'transaction_details_standard.id',
                    '=',
                    'transactions.config_id'
                )
                ->join(
                    'categories',
                    'categories.id',
                    '=',
                    'transaction_items.category_id'
                )
                ->select(
                    'categories.id',
                )
                ->when($request->missing('withInactive'), function ($query) {
                    $query->where('categories.active', true);
                })
                ->where('categories.user_id', $user->id)
                ->when($request->has('payee'), function ($query) use ($request) {
                    $query->whereRaw(
                        '(transaction_details_standard.account_from_id = ? OR transaction_details_standard.account_to_id = ?)',
                        [$request->get('payee'), $request->get('payee')],
                    );
                })
                ->groupBy('categories.id')
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->pluck('id')
                ->toArray();

            $categories = Category::findMany($results)
                ->sortBy(fn ($category) => array_search($category->getKey(), $results))
                ->map(function ($category) {
                    $category->text = $category->full_name;

                    return $category->only(['id', 'text']);
                })
                ->values();
        }

        return response()
            ->json(
                $categories,
                Response::HTTP_OK
            );
    }

    public function getFullList(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/categories')
         * @middlewares('api', 'auth:sanctum')
         */
        $categories = $request->user()
            ->categories()
            ->when($request->missing('withInactive'), function ($query) {
                $query->active();
            })
            ->get();

        return response()
            ->json(
                $categories,
                Response::HTTP_OK
            );
    }

    public function getItem(Category $category): JsonResponse
    {
        /**
         * @get('/api/assets/category/{category}')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('view', $category);

        return response()
            ->json(
                $category,
                Response::HTTP_OK
            );
    }

    public function updateActive(Category $category, $active): JsonResponse
    {
        /**
         * @put('/api/assets/category/{category}/active/{active}')
         * @name('api.category.updateActive')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('update', $category);

        $category->active = $active;
        $category->save();

        return response()
            ->json(
                $category,
                Response::HTTP_OK
            );
    }
}
