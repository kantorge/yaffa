<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Response;

class TransactionApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function reconcile(Transaction $transaction, $newState)
    {
        $this->authorize('update', $transaction);

        $transaction->reconciled = $newState;
        $transaction->save();

        return response()->json(
            [
                'success' => true,
            ],
            Response::HTTP_OK
        );
    }
}
