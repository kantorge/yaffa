<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TagApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * Get a list of tags with optional search filtering.
     */
    public function getList(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/tags")
         * @name("api.v1.tags.list")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        $tags = $request->user()
            ->tags()
            ->when($request->missing('withInactive'), function ($query) {
                $query->where('active', true);
            })
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        // Return fetched data
        return response()->json($tags, Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function getItem(Tag $tag): JsonResponse
    {
        /**
         * @get("/api/v1/tags/{tag}")
         * @name("api.v1.tags.item")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('view', $tag);

        return response()
            ->json(
                $tag,
                Response::HTTP_OK
            );
    }

    /**
     * V1: PATCH /api/v1/tags/{tag}
     * @name("api.v1.tags.patchActive")
     * Accepts { active: true|false } in request body.
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
}
