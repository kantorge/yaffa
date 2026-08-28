<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryLearningMergeRequest;
use App\Http\Requests\CategoryLearningRequest;
use App\Http\Resources\CategoryLearningResource;
use App\Models\CategoryLearning;
use App\Models\User;
use App\Services\CategoryLearningManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
                'index', 'show',
            ])]
#[Middleware('abilities:write', only: [
                'store', 'update', 'deactivate', 'activate', 'destroy', 'merge',
            ])]
class CategoryLearningApiController extends Controller
{
    public function __construct(private CategoryLearningManagementService $managementService) {}

    /**
     * List category learnings
     *
     * @throws AuthorizationException
     */
    public function index(CategoryLearningRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', CategoryLearning::class);

        $items = $this->managementService->getList($user, $request->validated());

        return response()->json(CategoryLearningResource::collection($items)->resolve(), Response::HTTP_OK);
    }

    /**
     * Get a category learning
     *
     * @throws AuthorizationException
     */
    public function show(Request $request, CategoryLearning $categoryLearning): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $categoryLearning);

        if ($categoryLearning->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return response()->json(
            new CategoryLearningResource($categoryLearning->load('category'))->resolve(),
            Response::HTTP_OK
        );
    }

    /**
     * Create a category learning
     *
     * Creates a new learned payee-to-category mapping, or updates the existing one
     * if a matching mapping already exists for the user.
     *
     * @throws AuthorizationException
     */
    public function store(CategoryLearningRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', CategoryLearning::class);

        $result = $this->managementService->store($user, $request->validated());

        return response()->json(
            new CategoryLearningResource($result['learning'])->resolve(),
            $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK
        );
    }

    /**
     * Update a category learning
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'categoryLearning')]
    public function update(CategoryLearningRequest $request, CategoryLearning $categoryLearning): JsonResponse
    {
        $learning = $this->managementService->update($categoryLearning, $request->validated());

        return response()->json(new CategoryLearningResource($learning)->resolve(), Response::HTTP_OK);
    }

    /**
     * Deactivate a category learning
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'categoryLearning')]
    public function deactivate(CategoryLearning $categoryLearning): JsonResponse
    {
        $learning = $this->managementService->deactivate($categoryLearning);

        return response()->json(new CategoryLearningResource($learning)->resolve(), Response::HTTP_OK);
    }

    /**
     * Activate a category learning
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'categoryLearning')]
    public function activate(CategoryLearning $categoryLearning): JsonResponse
    {
        $learning = $this->managementService->activate($categoryLearning);

        return response()->json(new CategoryLearningResource($learning)->resolve(), Response::HTTP_OK);
    }

    /**
     * Delete a category learning
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'categoryLearning')]
    public function destroy(CategoryLearning $categoryLearning): JsonResponse
    {
        $this->managementService->destroy($categoryLearning);

        return response()->json([
            'categoryLearning' => $categoryLearning,
        ], Response::HTTP_OK);
    }

    /**
     * Merge two category learnings
     *
     * Merges the source learning into the target learning and removes the source.
     *
     * @throws AuthorizationException
     */
    public function merge(CategoryLearningMergeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $source = CategoryLearning::query()->findOrFail((int) $validated['source_id']);
        $target = CategoryLearning::query()->findOrFail((int) $validated['target_id']);

        Gate::authorize('update', $source);
        Gate::authorize('update', $target);

        $merged = $this->managementService->merge($source, $target);

        return response()->json(new CategoryLearningResource($merged)->resolve(), Response::HTTP_OK);
    }
}
