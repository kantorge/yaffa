<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AccountEntityApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
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
        Gate::authorize('update', $accountEntity);

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
     * @throws AuthorizationException
     */
    public function destroy(AccountEntity $accountEntity): JsonResponse
    {
        /**
         * @delete('/api/accountentity/{accountEntity}')
         * @name('api.accountentity.destroy')
         * @middlewares('web', 'auth', 'verified')
         */
        Gate::authorize('forceDelete', $accountEntity);

        try {
            $accountEntity->delete();
            $accountEntity->config->delete();

            return response()
                ->json(
                    ['accountEntity' => $accountEntity],
                    Response::HTTP_OK
                );
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                $error = __(
                    ':type is in use, cannot be deleted',
                    ['type' => __(Str::ucfirst($accountEntity->config_type))]
                );
            } else {
                $error = __('Database error:') . ' ' . $e->errorInfo[2];
            }
        }

        return response()
            ->json(
                [
                    'accountEntity' => $accountEntity,
                    'error' => $error,
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
    }
}
