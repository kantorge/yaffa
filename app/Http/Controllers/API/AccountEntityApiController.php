<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:write', only: [
    'patchActive', 'destroy', 'recalculateAccountMonthlySummaries',
])]
class AccountEntityApiController extends Controller
{
    /**
     * Update account entity active status
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'accountEntity')]
    public function patchActive(Request $request, AccountEntity $accountEntity): JsonResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $accountEntity->active = $validated['active'];
        $accountEntity->save();

        return response()->json($accountEntity, Response::HTTP_OK);
    }

    /**
     * Recalculate account monthly summaries
     *
     * Queues a background job to recalculate the cached monthly summaries for all
     * accounts belonging to the current user. Stale/stuck batches from a previous run are
     * cancelled by the command itself (CalculateAccountMonthlySummaries) before it dispatches
     * fresh ones, so every caller of that command gets the same guard.
     */
    public function recalculateAccountMonthlySummaries(Request $request): JsonResponse
    {
        Artisan::queue('app:cache:account-monthly-summaries', [
            'userId' => $request->user()->id,
        ]);

        return response()->json([
            'message' => __('maintenance.accountMonthlySummaries.queued'),
        ], Response::HTTP_OK);
    }

    /**
     * Delete an account entity
     *
     * @throws AuthorizationException
     */
    #[Authorize('forceDelete', 'accountEntity')]
    public function destroy(AccountEntity $accountEntity): JsonResponse
    {
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
