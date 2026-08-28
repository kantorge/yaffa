<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\User;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
                'index', 'getItem',
            ])]
#[Middleware('abilities:write', only: [
                'store', 'update', 'destroy',
            ])]
class BudgetApiController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    /**
     * Get a list of the user's standalone budgets.
     */
    #[Authorize('viewAny', Budget::class)]
    public function index(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/budgets")
         * @name("api.v1.budgets.index")
         * @middlewares("api", "auth:sanctum")
         */
        /** @var User $user */
        $user = $request->user();

        $budgets = $user->budgets()->with(['category', 'account'])->get();

        return response()->json($budgets, Response::HTTP_OK);
    }

    /**
     * Get a budget by ID.
     */
    #[Authorize('view', 'budget')]
    public function getItem(Budget $budget): JsonResponse
    {
        /**
         * @get("/api/v1/budgets/{budget}")
         * @name("api.v1.budgets.show")
         * @middlewares("api", "auth:sanctum")
         */
        $budget->load(['category', 'account.config.currency']);

        return response()->json($budget, Response::HTTP_OK);
    }

    /**
     * Store a newly created budget in storage.
     *
     * @post("/api/v1/budgets")
     * @name("api.v1.budgets.store")
     * @middlewares("api", "auth:sanctum")
     */
    #[Authorize('create', Budget::class)]
    public function store(BudgetRequest $request): JsonResponse
    {
        $budget = $this->budgetService->store($request->user(), $request->validated());

        return response()->json($budget, Response::HTTP_CREATED);
    }

    /**
     * Update an existing budget in storage.
     *
     * @patch("/api/v1/budgets/{budget}")
     * @name("api.v1.budgets.update")
     * @middlewares("api", "auth:sanctum")
     */
    #[Authorize('update', 'budget')]
    public function update(BudgetRequest $request, Budget $budget): JsonResponse
    {
        $budget = $this->budgetService->update($budget, $request->validated());

        return response()->json($budget, Response::HTTP_OK);
    }

    /**
     * Delete a budget.
     */
    #[Authorize('delete', 'budget')]
    public function destroy(Budget $budget): JsonResponse
    {
        /**
         * @delete("/api/v1/budgets/{budget}")
         * @name("api.v1.budgets.destroy")
         * @middlewares("api", "auth:sanctum")
         */
        $result = $this->budgetService->delete($budget);

        if ($result['success']) {
            return response()->json(['budget' => $budget], Response::HTTP_OK);
        }

        return response()->json(
            [
                'budget' => $budget,
                'error' => $result['error'],
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
