<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TagApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getList(Request $request)
    {
        $tags = Auth::user()
            ->tags()
            ->active()
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
}
