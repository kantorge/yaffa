<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Services\AssetOverviewService;
use App\Services\PayeeCategoryStatsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
    'categoryStats', 'overview',
])]
class PayeeStatsApiController extends Controller
{
    public function __construct(
        private PayeeCategoryStatsService $payeeCategoryStatsService,
        private AssetOverviewService $assetOverviewService,
    ) {
    }

    /**
     * Get payee lifetime overview
     *
     * Returns the number of transactions, first and last transaction date, and the total paid
     * to and received from the payee (in the base currency). Schedules are excluded.
     */
    public function overview(Request $request, AccountEntity $accountEntity): JsonResponse
    {
        if (! $accountEntity->isPayee() || $accountEntity->user_id !== $request->user()->id) {
            return response()->json([
                'error' => __('Payee not found'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            $this->assetOverviewService->payeeOverview($request->user(), $accountEntity),
            Response::HTTP_OK
        );
    }

    /**
     * Get payee category stats
     *
     * Returns the most frequently used categories for a payee, based on the
     * payee's transaction history over a recent period.
     *
     * @throws AuthorizationException
     */
    public function categoryStats(Request $request, AccountEntity $accountEntity): JsonResponse
    {
        $validated = $request->validate([
            'transaction_type' => ['nullable', 'in:withdrawal,deposit'],
        ]);

        $user = $request->user();

        if (! $accountEntity->isPayee() || $accountEntity->user_id !== $user->id) {
            return response()->json([
                'error' => __('Payee not found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $transactionType = isset($validated['transaction_type'])
            ? TransactionType::from($validated['transaction_type'])
            : null;

        $categories = $this->payeeCategoryStatsService
            ->getCategoryStatsForPayee($user, $accountEntity, 6, $transactionType);
        $deferredCategoryIds = $accountEntity->deferredCategories()
            ->pluck('categories.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return response()->json([
            'payee_id' => $accountEntity->id,
            'payee_name' => $accountEntity->name,
            'categories' => $categories,
            'deferred_category_ids' => $deferredCategoryIds,
            'period_months' => 6,
        ], Response::HTTP_OK);
    }
}
