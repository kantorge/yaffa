<?php

namespace App\Http\Controllers\API;

use App\Exceptions\CurrencyRateConversionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRateRequest;
use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Services\CurrencyRateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
                'index',
            ])]
#[Middleware('abilities:write', only: [
                'store', 'update', 'destroy', 'retrieveMissingCurrencyRateToBase',
            ])]
#[Middleware('abilities:settings', only: [
                'clearCache',
            ])]
class CurrencyRateApiController extends Controller
{
    use CurrencyTrait;
    public function __construct(
        protected CurrencyRateService $currencyRateService
    ) {}

    /**
     * List currency rates
     *
     * Returns currency rates between two currencies, optionally filtered by
     * date range.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'from')]
    public function index(Request $request, Currency $from, Currency $to): JsonResponse
    {
        Gate::authorize('view', $to);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        if ($dateFrom || $dateTo) {
            $rates = $this->currencyRateService->getRatesByDateRange(
                $from->id,
                $to->id,
                $dateFrom,
                $dateTo
            );
        } else {
            $rates = $this->currencyRateService->getAllRates($from->id, $to->id);
        }

        return response()->json([
            'rates' => $rates,
        ]);
    }

    /**
     * Create a currency rate
     *
     * @throws AuthorizationException
     */
    public function store(CurrencyRateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fromCurrency = Currency::query()->findOrFail($validated['from_id']);
        $toCurrency = Currency::query()->findOrFail($validated['to_id']);

        Gate::authorize('update', $fromCurrency);
        Gate::authorize('update', $toCurrency);

        $rate = CurrencyRate::create($validated);

        return response()->json([
            'rate' => $rate->load(['currencyFrom', 'currencyTo']),
            'message' => __('Currency rate added'),
        ], 201);
    }

    /**
     * Update a currency rate
     *
     * @throws AuthorizationException
     */
    public function update(CurrencyRateRequest $request, CurrencyRate $currencyRate): JsonResponse
    {
        $validated = $request->validated();

        Gate::authorize('update', $currencyRate->currencyFrom);
        Gate::authorize('update', $currencyRate->currencyTo);

        $currencyRate->update($validated);

        return response()->json([
            'rate' => $currencyRate->fresh(['currencyFrom', 'currencyTo']),
            'message' => __('Currency rate updated'),
        ]);
    }

    /**
     * Delete a currency rate
     *
     * @throws AuthorizationException
     */
    public function destroy(CurrencyRate $currencyRate): JsonResponse
    {
        Gate::authorize('delete', $currencyRate->currencyFrom);
        Gate::authorize('delete', $currencyRate->currencyTo);

        $currencyRate->delete();

        return response()->json([
            'message' => __('Currency rate deleted'),
        ]);
    }

    /**
     * Retrieve missing currency rate to base
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'currency')]
    public function retrieveMissingCurrencyRateToBase(Currency $currency): JsonResponse
    {
        // Authorize user access to requested currency
        try {
            $currency->retrieveMissingCurrencyRateToBase();
        } catch (CurrencyRateConversionException $e) {
            return response()->json(
                [
                    'error' => [
                        'code' => 'CONVERSION_ERROR',
                        'message' => $e->getMessage(),
                    ],
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response()->json();
    }

    /**
     * Clear currency cache
     *
     * Clears all currency-related caches for the current user. This includes
     * the monthly average rates and individual currency lists.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $this->clearCurrencyCache($request->user()->id);

        return response()->json([
            'message' => __('maintenance.currencyCache.cleared'),
        ], Response::HTTP_OK);
    }
}
