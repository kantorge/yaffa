<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OnboardingApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function getOnboardingData(Request $request): JsonResponse
    {
        return response()->json(
            [
                'dismissed' => $request->user()->hasFlag('dismissOnboardingDashboardWidget'),
                'steps' => $request->user()->onboarding()->steps(),
            ],
            Response::HTTP_OK
        );
    }

    public function setDismissedFlag(Request $request): Response
    {
        $request->user()->flag('dismissOnboardingDashboardWidget');

        return response('',Response::HTTP_OK);
    }
}
