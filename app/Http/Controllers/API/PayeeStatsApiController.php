<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payee;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\Response;

class PayeeStatsApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    /**
     * GET /api/ai/payees/{id}/category-stats - Get category usage stats for a payee
     *
     * @throws AuthorizationException
     */
    public function categoryStats(Request $request, Payee $payee): JsonResponse
    {
        $user = $request->user();

        // Ensure payee belongs to user
        if ($payee->user_id !== $user->id) {
            return response()->json([
                'error' => __('Payee not found'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Query transactions for this payee in the last 6 months
        $sixMonthsAgo = now()->subMonths(6)->startOfDay();

        $stats = $payee->transactions()
            ->where('created_at', '>=', $sixMonthsAgo)
            ->whereHas('transactionDetailStandard', function (Builder $query) {
                $query->whereNotNull('payee_id'); // Only standard transactions with payees
            })
            ->with(['transactionItems' => function ($query) {
                $query->select('transaction_id', 'category_id')
                    ->distinct();
            }])
            ->get()
            ->pluck('transactionItems.*.category_id')
            ->flatten()
            ->countBy()
            ->sort()
            ->reverse();

        // Format response
        $categories = $stats->map(fn ($count, $categoryId) => [
            'category_id' => (int) $categoryId,
            'usage_count' => $count,
        ])->values();

        return response()->json([
            'payee_id' => $payee->id,
            'payee_name' => $payee->name,
            'categories' => $categories,
            'period_months' => 6,
        ], Response::HTTP_OK);
    }
}
