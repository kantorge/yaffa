<?php

namespace App\Http\Controllers\API;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Models\AccountGroup;
use App\Services\AccountGroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AccountGroupApiController extends Controller implements HasMiddleware
{
    protected AccountGroupService $accountGroupService;

    public function __construct()
    {

        $this->accountGroupService = new AccountGroupService();
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
        ];
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
        Gate::authorize('delete', $accountGroup);
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
