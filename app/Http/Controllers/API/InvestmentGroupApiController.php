<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Models\InvestmentGroup;
use App\Services\InvestmentGroupService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InvestmentGroupApiController extends Controller
{
    protected InvestmentGroupService $investmentGroupService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->investmentGroupService = new InvestmentGroupService();
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(InvestmentGroup $investmentGroup): JsonResponse
    {
        /**
         * @delete('/api/assets/investmentgroup/{investmentGroup}')
         * @name('api.investmentgroup.destroy')
         * @middlewares('api', 'auth:sanctum')
         */
        Gate::authorize('delete', $investmentGroup);
        $result = $this->investmentGroupService->delete($investmentGroup);

        if ($result['success']) {
            return response()
                ->json(
                    ['investmentGroup' => $investmentGroup],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'investmentGroup' => $investmentGroup,
                    'error' => $result['error'],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
    }
}
