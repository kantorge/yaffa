<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryLearningMergeRequest;
use App\Http\Requests\CategoryLearningRequest;
use App\Http\Resources\CategoryLearningResource;
use App\Models\CategoryLearning;
use App\Models\User;
use App\Services\CategoryLearningManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CategoryLearningApiController extends Controller implements HasMiddleware
{
    public function __construct(private CategoryLearningManagementService $managementService)
    {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function index(CategoryLearningRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('viewAny', CategoryLearning::class);

        $items = $this->managementService->getList($user, $request->validated());

        return response()->json(CategoryLearningResource::collection($items)->resolve(), Response::HTTP_OK);
    }

    public function show(Request $request, CategoryLearning $categoryLearning): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $categoryLearning);

        abort_if($categoryLearning->user_id !== $user->id, Response::HTTP_FORBIDDEN);

        return response()->json(
            new CategoryLearningResource($categoryLearning->load('category'))->resolve(),
            Response::HTTP_OK
        );
    }

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

    public function update(CategoryLearningRequest $request, CategoryLearning $categoryLearning): JsonResponse
    {
        Gate::authorize('update', $categoryLearning);

        $learning = $this->managementService->update($categoryLearning, $request->validated());

        return response()->json(new CategoryLearningResource($learning)->resolve(), Response::HTTP_OK);
    }

    public function deactivate(CategoryLearning $categoryLearning): JsonResponse
    {
        Gate::authorize('update', $categoryLearning);

        $learning = $this->managementService->deactivate($categoryLearning);

        return response()->json(new CategoryLearningResource($learning)->resolve(), Response::HTTP_OK);
    }

    public function activate(CategoryLearning $categoryLearning): JsonResponse
    {
        Gate::authorize('update', $categoryLearning);

        $learning = $this->managementService->activate($categoryLearning);

        return response()->json(new CategoryLearningResource($learning)->resolve(), Response::HTTP_OK);
    }

    public function destroy(CategoryLearning $categoryLearning): JsonResponse
    {
        Gate::authorize('delete', $categoryLearning);

        $this->managementService->destroy($categoryLearning);

        return response()->json([
            'categoryLearning' => $categoryLearning,
        ], Response::HTTP_OK);
    }

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
