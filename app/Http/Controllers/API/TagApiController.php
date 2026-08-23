<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TagApiController extends Controller implements HasMiddleware
{
    public function __construct(protected TagService $tagService)
    {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
            new Middleware('abilities:read', only: [
                'getList', 'getItem',
            ]),
            new Middleware('abilities:write', only: [
                'patchActive', 'destroy',
            ]),
        ];
    }

    /**
     * List tags
     *
     * Returns up to 10 tags for the current user, optionally filtered by name
     * (`q`) and restricted to active tags unless `withInactive` is present.
     */
    public function getList(Request $request): JsonResponse
    {
        $tags = $request->user()
            ->tags()
            ->when($request->missing('withInactive'), function ($query) {
                $query->where('active', true);
            })
            ->select(['id', 'name AS text'])
            ->when($request->query('q'), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->query('q') . '%');
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        // Return fetched data
        return response()->json($tags, Response::HTTP_OK);
    }

    /**
     * Get a tag
     *
     * @throws AuthorizationException
     */
    public function getItem(Tag $tag): JsonResponse
    {
        Gate::authorize('view', $tag);

        return response()
            ->json(
                $tag,
                Response::HTTP_OK
            );
    }

    /**
     * Update tag active status
     *
     * Accepts { active: true|false } in the request body.
     *
     * @throws AuthorizationException
     */
    public function patchActive(Request $request, Tag $tag): JsonResponse
    {
        Gate::authorize('update', $tag);

        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $tag->active = $validated['active'];
        $tag->save();

        return response()->json($tag, Response::HTTP_OK);
    }

    /**
     * Delete a tag
     *
     * @throws AuthorizationException
     */
    public function destroy(Tag $tag): JsonResponse
    {
        Gate::authorize('delete', $tag);

        $this->tagService->delete($tag);

        return response()->json(['tag' => $tag], Response::HTTP_OK);
    }
}
