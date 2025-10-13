<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TagApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function getList(Request $request): JsonResponse
    {
        /**
         * @get('/api/assets/tag')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $tags = $request->user()
            ->tags()
            ->when($request->missing('withInactive'), function ($query) {
                $query->active();
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
         * @get('/api/assets/tag/{tag}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        Gate::authorize('view', $tag);

        return response()
            ->json(
                $tag,
                Response::HTTP_OK
            );
    }

    /**
     * @throws AuthorizationException
     */
    public function updateActive(Tag $tag, string $active): JsonResponse
    {
        /**
         * @put('/api/assets/tag/{tag}/active/{active}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        Gate::authorize('update', $tag);

        $tag->active = $active === '1';
        $tag->save();

        return response()
            ->json(
                $tag,
                Response::HTTP_OK
            );
    }
}
