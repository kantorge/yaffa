<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionType;
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
