<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TransactionTypeApiController extends Controller
{
    /**
     * Get all transaction types as JSON for frontend consumption
     */
    public function index(): JsonResponse
    {
        return response()->json(TransactionType::all());
    }
}
