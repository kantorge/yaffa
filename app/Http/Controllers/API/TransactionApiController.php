<?php

namespace App\Http\Controllers\API;

use App\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionApiController extends Controller
{
    public function reconcile(Transaction $transaction, $newState) {
        $transaction->reconciled = $newState;
        $transaction->save();

        return response()->json([
                "success" => true
            ], Response::HTTP_OK);
    }
}
