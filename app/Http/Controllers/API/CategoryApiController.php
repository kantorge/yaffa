<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CategoryApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getList(Request $request)
    {
        $query = $request->get('q');
        if ($query) {
            $categories = Auth::user()
                ->categories()
                ->active()
                ->get()
                ->filter(function ($category) use ($query) {
                    return stripos($category->full_name, $query) !== false;
                })
                ->sortBy('full_name')
                ->take(10)
                ->map(function ($category) {
                    $category->text = $category->full_name;

                    return $category->only(['id', 'text']);
                })
                ->values();
        } else {
            $results = DB::table('transaction_items')
                ->join(
                    'transactions',
                    'transactions.id',
                    '=',
                    'transaction_items.transaction_id'
                )
                ->join(
                    'transaction_details_standard',
                    'transaction_details_standard.id',
                    '=',
                    'transactions.config_id'
                )
                ->join(
                    'categories',
                    'categories.id',
                    '=',
                    'transaction_items.category_id'
                )
                ->select(
                    'categories.id',
                )
                ->where('categories.active', true)
                ->where('categories.user_id', Auth::user()->id)
                ->when($request->has('payee'), function ($query) use ($request) {
                    $query->whereRaw(
                        '(transaction_details_standard.account_from_id = ? OR transaction_details_standard.account_to_id = ?)',
                        [$request->get('payee'), $request->get('payee')],
                    );
                })
                ->groupBy('categories.id')
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->pluck('id')
                ->toArray();

            $categories = Category::findMany($results)
            ->sortBy(function ($category) use ($results) {
                return array_search($category->getKey(), $results);
            })
            ->map(function ($category) {
                $category->text = $category->full_name;

                return $category->only(['id', 'text']);
            })
            ->values();
        }

        return response()
            ->json(
                $categories,
                Response::HTTP_OK
            );
    }

    public function getItem(Category $category)
    {
        $this->authorize('view', $category);

        return response()
            ->json(
                $category,
                Response::HTTP_OK
            );
    }
}
