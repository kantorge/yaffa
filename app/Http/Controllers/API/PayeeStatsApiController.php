<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Services\PayeeCategoryStatsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\Response;

class PayeeStatsApiController extends Controller implements HasMiddleware
{
    public function __construct(private PayeeCategoryStatsService $payeeCategoryStatsService)
    {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * GET /api/ai/payees/{id}/category-stats - Get category usage stats for a payee
     *
     * @throws AuthorizationException
     */
    public function categoryStats(Request $request, AccountEntity $payee): JsonResponse
    {
        $user = $request->user();

        if (! $payee->isPayee() || $payee->user_id !== $user->id) {
            return response()->json([
                'error' => __('Payee not found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $categories = $this->payeeCategoryStatsService->getCategoryStatsForPayee($user, $payee, 6);

        return response()->json([
            'payee_id' => $payee->id,
            'payee_name' => $payee->name,
            'categories' => $categories,
            'period_months' => 6,
        ], Response::HTTP_OK);
    }
}
