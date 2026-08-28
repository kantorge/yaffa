<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InvestmentGroup;
use App\Services\InvestmentGroupService;
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
class InvestmentGroupApiController extends Controller
{
    protected InvestmentGroupService $investmentGroupService;

    public function __construct()
    {

        $this->investmentGroupService = new InvestmentGroupService();
    }

    /**
     * Delete an investment group
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'investmentGroup')]
    public function destroy(InvestmentGroup $investmentGroup): JsonResponse
    {
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
