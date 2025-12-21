<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentPriceRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;

class InvestmentPriceApiController extends Controller implements HasMiddleware
{
    public function __construct(
        protected InvestmentService $investmentService
    ) {
    }

    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    /**
     * Get investment prices, optionally filtered by date range.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request, Investment $investment): JsonResponse
    {
        Gate::authorize('view', $investment);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = InvestmentPrice::where('investment_id', $investment->id)
            ->orderBy('date');

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        $prices = $query->get();

        return response()->json([
            'prices' => $prices,
        ]);
    }

    /**
     * Store a new investment price.
     *
     * @throws AuthorizationException
     */
    public function store(InvestmentPriceRequest $request): JsonResponse
    {
        $investment = Investment::find($request->investment_id);
        Gate::authorize('view', $investment);

        $validated = $request->validated();

        $price = InvestmentPrice::create($validated);

        // Recalculate related accounts
        $this->investmentService->recalculateRelatedAccounts($investment);

        return response()->json([
            'price' => $price->load('investment'),
            'message' => __('Investment price added'),
        ], 201);
    }

    /**
     * Update an existing investment price.
     *
     * @throws AuthorizationException
     */
    public function update(InvestmentPriceRequest $request, InvestmentPrice $investmentPrice): JsonResponse
    {
        Gate::authorize('view', $investmentPrice->investment);

        $validated = $request->validated();

        $investmentPrice->update($validated);

        // Recalculate related accounts
        $this->investmentService->recalculateRelatedAccounts($investmentPrice->investment);

        return response()->json([
            'price' => $investmentPrice->fresh(['investment']),
            'message' => __('Investment price updated'),
        ]);
    }

    /**
     * Delete an investment price.
     *
     * @throws AuthorizationException
     */
    public function destroy(InvestmentPrice $investmentPrice): JsonResponse
    {
        Gate::authorize('view', $investmentPrice->investment);

        $investment = $investmentPrice->investment;
        $investmentPrice->delete();

        // Recalculate related accounts
        $this->investmentService->recalculateRelatedAccounts($investment);

        return response()->json([
            'message' => __('Investment price deleted'),
        ]);
    }

    /**
     * Retrieve missing investment prices from the provider.
     *
     * @throws AuthorizationException
     */
    public function retrieveMissingPrices(Investment $investment): JsonResponse
    {
        Gate::authorize('view', $investment);

        // Get latest known date of price date, so we can retrieve missing values
        $lastPrice = $investment->investmentPrices->last();
        $date = $lastPrice ? $lastPrice->date : Carbon::now()->subDays(30);

        $investment->getInvestmentPriceFromProvider($date);

        // Recalculate related accounts
        $this->investmentService->recalculateRelatedAccounts($investment);

        return response()->json([
            'message' => __('Investment prices successfully downloaded from :date', ['date' => $date->toFormattedDateString()]),
        ]);
    }

    /**
     * Check if a price exists for a specific date and investment.
     *
     * @throws AuthorizationException
     */
    public function checkPrice(Request $request, Investment $investment): JsonResponse
    {
        Gate::authorize('view', $investment);

        $date = $request->query('date');

        if (!$date) {
            return response()->json([
                'exists' => false,
                'price' => null,
            ]);
        }

        $existingPrice = InvestmentPrice::where('investment_id', $investment->id)
            ->where('date', $date)
            ->first();

        return response()->json([
            'exists' => $existingPrice !== null,
            'price' => $existingPrice ? $existingPrice->price : null,
        ]);
    }
}
