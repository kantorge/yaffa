<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use Illuminate\Http\Response;

class AccountEntityApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function updateActive(AccountEntity $accountEntity, $active)
    {
        $this->authorize('update', $accountEntity);

        $accountEntity->active = $active;
        $accountEntity->save();

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }
}