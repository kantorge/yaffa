<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountGroup;
use App\Services\AccountGroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AccountGroupApiController extends Controller
{
    protected AccountGroupService $accountGroupService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->accountGroupService = new AccountGroupService();
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(AccountGroup $accountGroup): JsonResponse
    {
        /**
         * @delete('/api/assets/accountgroup/{accountGroup}')
         * @name('api.accountgroup.destroy')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('delete', $accountGroup);
        $result = $this->accountGroupService->delete($accountGroup);

        if ($result['success']) {
            return response()
                ->json(
                    ['accountGroup' => $accountGroup],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'accountGroup' => $accountGroup,
                    'error' => $result['error'],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
    }
}
