<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\CheckPriceInvestmentPriceApiRequest;
use App\Http\Requests\InvestmentPriceRequest;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
    'index', 'checkPrice',
])]
#[Middleware('abilities:write', only: [
    'store', 'update', 'destroy', 'retrieveMissingPrices',
])]
class InvestmentPriceApiController extends Controller
{
    public function __construct(
        protected InvestmentService $investmentService
    ) {
    }

    /**
     * List investment prices
     *
     * Returns investment prices for the given investment, optionally filtered by date range.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'investment')]
    public function index(Request $request, Investment $investment): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = InvestmentPrice::where('investment_id', $investment->id)
            ->with('investment.currency')
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
     * Add an investment price
     *
     * Creates a new price entry for the investment and recalculates related account balances.
     *
     * @throws AuthorizationException
     */
    public function store(InvestmentPriceRequest $request): JsonResponse
    {
        $investment = Investment::findOrFail($request->investment_id);
        Gate::authorize('update', $investment);

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
     * Update an investment price
     *
     * Updates the price entry and recalculates related account balances.
     *
     * @throws AuthorizationException
     */
    public function update(InvestmentPriceRequest $request, InvestmentPrice $investmentPrice): JsonResponse
    {
        Gate::authorize('update', $investmentPrice->investment);

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
     * Delete an investment price
     *
     * Deletes the price entry and recalculates related account balances.
     *
     * @throws AuthorizationException
     */
    public function destroy(InvestmentPrice $investmentPrice): JsonResponse
    {
        Gate::authorize('delete', $investmentPrice->investment);

        $investment = $investmentPrice->investment;
        $investmentPrice->delete();

        // Recalculate related accounts
        $this->investmentService->recalculateRelatedAccounts($investment);

        return response()->json([
            'message' => __('Investment price deleted'),
        ]);
    }

    /**
     * Retrieve missing investment prices
     *
     * Downloads investment prices from the configured provider, starting from the latest
     * known price date (or 30 days ago if none exist), and recalculates related account balances.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'investment')]
    public function retrieveMissingPrices(Investment $investment): JsonResponse
    {
        // Get latest known date of price date, so we can retrieve missing values
        /** @var InvestmentPrice|null $lastPrice */
        $lastPrice = $investment->investmentPrices()->latest('date')->first();
        $date = $lastPrice ? $lastPrice->date : Carbon::now()->subDays(30);

        $this->investmentService->fetchAndSavePrices($investment, $date);

        // Recalculate related accounts
        $this->investmentService->recalculateRelatedAccounts($investment);

        return response()->json([
            'message' => __('Investment prices successfully downloaded from :date', ['date' => $date->toFormattedDateString()]),
        ]);
    }

    /**
     * Check if a price exists
     *
     * Checks whether a price exists for the investment on a specific date, and returns
     * its value if found.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'investment')]
    public function checkPrice(CheckPriceInvestmentPriceApiRequest $request, Investment $investment): JsonResponse
    {
        $validated = $request->validated();

        $existingPrice = InvestmentPrice::where('investment_id', $investment->id)
            ->where('date', $validated['date'])
            ->first();

        return response()->json([
            'exists' => $existingPrice !== null,
            // Emitted as a decimal string, not the raw Money object, to match the
            // wire format Eloquent's SerializesCastableAttributes gives this same
            // field everywhere else (Money::jsonSerialize() would otherwise emit
            // {"amount": ..., "currency": ...} instead).
            'price' => $existingPrice?->price !== null ? (string) $existingPrice->price->getAmount() : null,
        ]);
    }
}
