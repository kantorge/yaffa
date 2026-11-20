<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\RechargeTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: ['summary'])]
class RechargeApiController extends Controller
{
    public function __construct(private readonly RechargeTagService $rechargeTagService)
    {
    }

    /**
     * Recharge summary
     *
     * `enabled` is false while recharge tags are disabled in the user settings or no category requires a tag. Otherwise returns the number of items in a
     * tag-requiring category without a tag, the number of tags in an "expect nil" category whose
     * balance (base currency) is not within 0.01 of zero, and the settled tags that are still active.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->rechargeTagService->isEnabled($user) || ! $this->rechargeTagService->hasTagRequiredCategories($user)) {
            return response()->json(['enabled' => false], Response::HTTP_OK);
        }

        return response()->json(
            ['enabled' => true, ...$this->rechargeTagService->summary($user)],
            Response::HTTP_OK
        );
    }
}
