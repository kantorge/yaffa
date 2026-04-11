<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TestInvestmentPriceProviderFetchRequest;
use App\Models\Investment;
use App\Services\InvestmentPriceProviderContextResolver;
use App\Services\InvestmentProviderAvailabilityService;
use App\Services\InvestmentPriceProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvestmentPriceProviderApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function __construct(
        private InvestmentProviderAvailabilityService $availabilityService,
        private InvestmentPriceProviderRegistry $providerRegistry,
        private InvestmentPriceProviderContextResolver $contextResolver,
    ) {
    }

    public function available(Request $request): JsonResponse
    {
        return response()->json(
            $this->availabilityService->forUser(
                $request->user(),
                $request->boolean('include_unavailable', false),
            ),
            Response::HTTP_OK,
        );
    }

    public function testFetch(TestInvestmentPriceProviderFetchRequest $request, string $providerKey): JsonResponse
    {
        if (! $this->providerRegistry->has($providerKey)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('Unknown investment price provider.'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $providerSettings = is_array($validated['provider_settings'])
            ? $validated['provider_settings']
            : [];

        $investment = new Investment([
            'investment_price_provider' => $providerKey,
            'symbol' => (string) ($validated['symbol'] ?? ''),
            'provider_settings' => $providerSettings,
        ]);
        $investment->user_id = $request->user()->id;
        $investment->setRelation('user', $request->user());

        try {
            $context = $this->contextResolver->resolve($investment);
            $investment->provider_credentials = $context['credentials'];
            $prices = $context['provider']->fetchPrices($investment);

            $latestPrice = collect($prices)
                ->filter(fn ($item) => is_array($item) && isset($item['date'], $item['price']))
                ->sortByDesc('date')
                ->first();

            if (! is_array($latestPrice)) {
                return response()->json([
                    'error' => [
                        'code' => 'NO_PRICE_FOUND',
                        'message' => __('No price data returned by provider.'),
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'message' => __('Test fetch successful.'),
                'provider_key' => $providerKey,
                'symbol' => $investment->symbol,
                'price' => (float) $latestPrice['price'],
                'date' => (string) $latestPrice['date'],
            ], Response::HTTP_OK);
        } catch (Throwable $exception) {
            return response()->json([
                'error' => [
                    'code' => 'FETCH_FAILED',
                    'message' => $exception->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
