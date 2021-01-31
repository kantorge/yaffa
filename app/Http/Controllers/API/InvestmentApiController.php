<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvestmentApiController extends Controller
{
    public function __construct(Investment $investment)
    {
        $this->investment = $investment;
    }

    public function getList(Request $request)
    {
		$investments = $this->investment
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        //return data
        return response()->json($investments, Response::HTTP_OK);
    }
}
