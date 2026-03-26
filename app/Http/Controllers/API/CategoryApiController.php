<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\CategoryResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CategoryApiController extends Controller implements HasMiddleware
{
    protected CategoryService $categoryService;

    public function __construct()
    {

        $this->categoryService = new CategoryService();
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * Get a list of categories with optional search and usage-based ordering.
     */
    public function getList(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/categories")
         * @name("api.v1.categories.index")
         * @middlewares("api", "auth:sanctum")
         */
        $user = $request->user();

        $query = $request->get('q');
        if ($query && $query !== '*') {
            $categories = $user->categories()
                ->with('parent')
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
                    );
                })
                ->get()
                ->filter(fn ($category) => mb_stripos($category->full_name, $query) !== false)
                ->sortBy('full_name')
                ->take(10)
                ->values();
        } elseif ($query === '*') {
            $categories = $user->categories()
                ->with('parent')
                ->when($request->missing('withInactive'), function ($query) {
                    $query->active();
                })
                ->get()
                ->sortBy('full_name')
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

            $categories = Category::with('parent')->findMany($results)
                ->sortBy(fn ($category) => array_search($category->getKey(), $results))
                ->values();
        }

        $payload = $categories
            ->map(fn (Category $category): array => (new CategoryResource($category))->toArray($request))
            ->values();

        return response()
            ->json(
                $payload,
                Response::HTTP_OK
            );
    }

    /**
     * Get a category by ID.
     */
    public function getItem(Category $category): JsonResponse
    {
        /**
         * @get("/api/v1/categories/{category}")
         * @name("api.v1.categories.show")
         * @middlewares("api", "auth:sanctum")
         */
        Gate::authorize('view', $category);

        return response()
            ->json(
                $category,
                Response::HTTP_OK
            );
    }

    /**
     * Store a newly created category in storage.
     *
     * @post("/api/v1/categories")
     * @name("api.v1.categories.store")
     * @middlewares("api", "auth:sanctum")
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = $request->user()->categories()->create($request->validated());

        return response()->json($category, Response::HTTP_CREATED);
    }

    /**
     * V1: PATCH /api/v1/categories/{category}
     * Accepts { active: true|false } in request body.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function patchActive(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);

        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $category->active = $validated['active'];
        $category->save();

        return response()->json($category, Response::HTTP_OK);
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): JsonResponse
    {
        /**
         * @delete("/api/v1/categories/{category}")
         * @name("api.v1.categories.destroy")
         * @middlewares("api", "auth:sanctum")
         */
        Gate::authorize('delete', $category);
        $result = $this->categoryService->delete($category);

        if ($result['success']) {
            return response()
                ->json(
                    ['category' => $category],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'category' => $category,
                    'error' => $result['error'],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
    }
}
