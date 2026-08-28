<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
                'getList', 'getItem',
            ])]
#[Middleware('abilities:write', only: [
                'patchActive', 'destroy',
            ])]
class TagApiController extends Controller
{
    public function __construct(protected TagService $tagService) {}

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
    #[Authorize('view', 'tag')]
    public function getItem(Tag $tag): JsonResponse
    {
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
    #[Authorize('update', 'tag')]
    public function patchActive(Request $request, Tag $tag): JsonResponse
    {
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
    #[Authorize('delete', 'tag')]
    public function destroy(Tag $tag): JsonResponse
    {
        $this->tagService->delete($tag);

        return response()->json(['tag' => $tag], Response::HTTP_OK);
    }
}
