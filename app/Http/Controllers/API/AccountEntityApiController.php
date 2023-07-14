<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Services\AccountEntityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AccountEntityApiController extends Controller
{
    protected AccountEntityService $accountEntityService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->accountEntityService = new AccountEntityService();
    }

    /**
     * @throws AuthorizationException
     */
    public function updateActive(AccountEntity $accountEntity, $active): JsonResponse
    {
        /**
         * @put('/api/assets/accountentity/{accountEntity}/active/{active}')
         * @name('api.accountentity.updateActive')
         * @middlewares('api', 'auth:sanctum')
         */
        $this->authorize('update', $accountEntity);

        $accountEntity->active = $active;
        $accountEntity->save();

        return response()
            ->json(
                $accountEntity,
                Response::HTTP_OK
            );
    }

    /**
     * Remove the specified account entity.
     *
     * @param AccountEntity $accountEntity
     * @return JsonResponse
     */
    public function destroy(AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @delete('/api/accountentity/{accountEntity}')
         * @name('api.accountentity.destroy')
         * @middlewares('web', 'auth', 'verified')
         */
        $this->authorize('delete', $accountEntity);
        $result = $this->accountEntityService->delete($accountEntity);

        if ($result['success']) {
            return response()
                ->json(
                    ['accountEntity' => $accountEntity],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'accountEntity' => $accountEntity,
                    'error' => $result['error'],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
    }
}
