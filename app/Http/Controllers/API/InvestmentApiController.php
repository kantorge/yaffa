<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvestmentApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getList(Request $request)
    {
        $investments = Auth::user()
            ->investments()
            ->active()
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%'.$request->get('q').'%');
            })
            ->when($request->get('currency_id'), function ($query) use ($request) {
                $query->where('currency_id', '=', $request->get('currency_id'));
            })
            ->orderBy('name')
            ->take(10)
            ->get();

        // Return data
        return response()->json($investments, Response::HTTP_OK);
    }

    /**
     * Read and return the currency suffix of the currency associated to the provided investment
     *
     * @param App\Models\Investment $investment
     * @return string
     */
    public function getCurrencySuffix(Investment $investment)
    {
        $this->authorize('view', $investment);

        return $investment->currency->suffix;
    }

    /**
     * Read and return the details of a selected investment
     *
     * @param App\Models\Investment $investment
     * @return App\Models\Investment
     */
    public function getInvestmentDetails(Investment $investment)
    {
        $this->authorize('view', $investment);

        $investment->load(['currency']);

        return $investment;
    }

    public function getPriceHistory(Investment $investment)
    {
        $this->authorize('view', $investment);

        $prices = InvestmentPrice::where('investment_id', '=', $investment->id)
            ->select(['id', 'date', 'price'])
            ->orderBy('date')
            ->get();

        // Return data
        return response()->json($prices, Response::HTTP_OK);
    }
}
