<?php

namespace App\Http\Controllers\API;

use App\Exceptions\CurrencyRateConversionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRateRequest;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Services\CurrencyRateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;

class CurrencyRateApiController extends Controller implements HasMiddleware
{
    public function __construct(
        protected CurrencyRateService $currencyRateService
    ) {
    }

    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    /**
     * Get currency rates, optionally filtered by date range.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request, Currency $from, Currency $to): JsonResponse
    {
        Gate::authorize('view', $from);
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
     * Store a new currency rate.
     *
     * @throws AuthorizationException
     */
    public function store(CurrencyRateRequest $request): JsonResponse
    {
        // Note, that the CurrencyRateRequest validates that from_id and to_id exist and belong to the authenticated user.
        $rate = CurrencyRate::create($request->validated());

        return response()->json([
            'rate' => $rate->load(['currencyFrom', 'currencyTo']),
            'message' => __('Currency rate added'),
        ], 201);
    }

    /**
     * Update an existing currency rate.
     *
     * @throws AuthorizationException
     */
    public function update(CurrencyRateRequest $request, CurrencyRate $currencyRate): JsonResponse
    {
        // Note, that the CurrencyRateRequest validates that from_id and to_id exist and belong to the authenticated user.
        $currencyRate->update($request->validated());

        return response()->json([
            'rate' => $currencyRate->fresh(['currencyFrom', 'currencyTo']),
            'message' => __('Currency rate updated'),
        ]);
    }

    /**
     * Delete a currency rate.
     *
     * @throws AuthorizationException
     */
    public function destroy(CurrencyRate $currencyRate): JsonResponse
    {
        Gate::authorize('view', $currencyRate->currencyFrom);
        Gate::authorize('view', $currencyRate->currencyTo);

        $currencyRate->delete();

        return response()->json([
            'message' => __('Currency rate deleted'),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function retrieveMissingCurrencyRateToBase(Currency $currency): JsonResponse
    {
        // Authorize user access to requested currency
        Gate::authorize('view', $currency);

        try {
            $currency->retrieveMissingCurrencyRateToBase();
        } catch (CurrencyRateConversionException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response()->json();
    }
}
