<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountGroup;
use App\Services\AccountGroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:write', only: [
                'destroy',
            ])]
class AccountGroupApiController extends Controller
{
    protected AccountGroupService $accountGroupService;

    public function __construct()
    {

        $this->accountGroupService = new AccountGroupService();
    }

    /**
     * Delete an account group
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'accountGroup')]
    public function destroy(AccountGroup $accountGroup): JsonResponse
    {
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
