<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TagApiController extends Controller
{
    public function __construct(Tag $tag)
    {
        $this->tag = $tag;
    }

    public function getList(Request $request)
    {
        $tags = $this->tag
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
}
