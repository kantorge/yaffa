<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
    'getList', 'getItem',
])]
#[Middleware('abilities:write', only: [
    'store', 'patchActive', 'destroy',
])]
class CategoryApiController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct()
    {
        $this->categoryService = new CategoryService();
    }

    /**
     * List categories
     *
     * Returns categories matching an optional search term, ordered by name or,
     * when no term is given, by usage against the user's transactions.
     */
    public function getList(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $request->query('q');
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
                            $query->where('account_entity_id', $request->query('payee'))->where('preferred', false);
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
                        [$request->query('payee'), $request->query('payee')],
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
     * Get a category
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    #[Authorize('view', 'category')]
    public function getItem(Category $category): JsonResponse
    {
        return response()
            ->json(
                $category,
                Response::HTTP_OK
            );
    }

    /**
     * Create a category
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    #[Authorize('create', Category::class)]
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = $request->user()->categories()->create($request->validated());

        return response()->json($category, Response::HTTP_CREATED);
    }

    /**
     * Update category active status
     *
     * Accepts { active: true|false } in the request body.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    #[Authorize('update', 'category')]
    public function patchActive(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $category->active = $validated['active'];
        $category->save();

        return response()->json($category, Response::HTTP_OK);
    }

    /**
     * Delete a category
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    #[Authorize('delete', 'category')]
    public function destroy(Category $category): JsonResponse
    {
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
