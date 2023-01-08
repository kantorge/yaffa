<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TagApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function getList(Request $request)
    {
        /**
         * @get('/api/assets/tag')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $tags = Auth::user()
            ->tags()
            ->when($request->missing('withInactive'), function ($query) {
                $query->active();
            })
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%'.$request->get('q').'%');
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        // Return fetched data
        return response()->json($tags, Response::HTTP_OK);
    }

    public function getItem(Tag $tag)
    {
        /**
         * @get('/api/assets/tag/{tag}')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $this->authorize('view', $tag);

        return response()
            ->json(
                $tag,
                Response::HTTP_OK
            );
    }
}
