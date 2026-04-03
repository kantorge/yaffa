<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentProviderConfigRequest;
use App\Http\Resources\InvestmentProviderConfigResource;
use App\Models\InvestmentProviderConfig;
use App\Services\InvestmentPriceProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvestmentProviderConfigApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function __construct(private InvestmentPriceProviderRegistry $providerRegistry)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', InvestmentProviderConfig::class);

        $configs = $request->user()
            ->investmentProviderConfigs()
            ->orderBy('provider_key')
            ->get();

        return response()->json(
            InvestmentProviderConfigResource::collection($configs)->resolve(),
            Response::HTTP_OK
        );
    }

    public function show(Request $request, string $providerKey): JsonResponse
    {
        if (! $this->providerRegistry->has($providerKey)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('Unknown investment price provider.'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $config = $request->user()
            ->investmentProviderConfigs()
            ->where('provider_key', $providerKey)
            ->first();

        if (! $config) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('No provider configuration found for this provider.'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('view', $config);

        return response()->json(
            (new InvestmentProviderConfigResource($config))->resolve(),
            Response::HTTP_OK
        );
    }

    public function update(InvestmentProviderConfigRequest $request, string $providerKey): JsonResponse
    {
        $existing = $request->user()
            ->investmentProviderConfigs()
            ->where('provider_key', $providerKey)
            ->first();

        if ($existing === null) {
            Gate::authorize('create', InvestmentProviderConfig::class);
        } else {
            Gate::authorize('update', $existing);
        }

        $validated = $request->validated();
        $previousCredentials = is_array($existing?->credentials) ? $existing->credentials : [];
        $incomingCredentials = $validated['credentials'] ?? [];
        if (! is_array($incomingCredentials)) {
            $incomingCredentials = [];
        }

        // Filter out null credentials to prevent accidental overwrites
        $incomingCredentials = array_filter($incomingCredentials, fn ($value) => $value !== null);

        $attributes = [
            'enabled' => (bool) ($validated['enabled'] ?? ($existing ? $existing->enabled : true)),
            'options' => $validated['options'] ?? $existing?->options,
            'plan' => $validated['plan'] ?? $existing?->plan,
            'rate_limit_overrides' => $validated['rate_limit_overrides'] ?? $existing?->rate_limit_overrides,
            'credentials' => array_merge($previousCredentials, $incomingCredentials),
        ];

        if (! $existing) {
            $config = InvestmentProviderConfig::create([
                'user_id' => $request->user()->id,
                'provider_key' => $providerKey,
                ...$attributes,
            ]);

            return response()->json(
                (new InvestmentProviderConfigResource($config))->resolve(),
                Response::HTTP_CREATED
            );
        }

        $existing->update($attributes);

        return response()->json(
            (new InvestmentProviderConfigResource($existing))->resolve(),
            Response::HTTP_OK
        );
    }

    public function test(InvestmentProviderConfigRequest $request, string $providerKey): JsonResponse
    {
        if (! $this->providerRegistry->has($providerKey)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('Unknown investment price provider.'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $config = $request->user()
            ->investmentProviderConfigs()
            ->where('provider_key', $providerKey)
            ->first();

        $validated = $request->validated();
        $storedCredentials = is_array($config?->credentials) ? $config->credentials : [];
        $incomingCredentials = $validated['credentials'] ?? [];
        if (! is_array($incomingCredentials)) {
            $incomingCredentials = [];
        }

        $effectiveCredentials = array_merge($storedCredentials, $incomingCredentials);
        $requiredFields = $this->providerRegistry->getMetadata($providerKey)['userSettingsSchema']['required'] ?? [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $effectiveCredentials)
                || $effectiveCredentials[$field] === null
                || (is_string($effectiveCredentials[$field]) && mb_trim($effectiveCredentials[$field]) === '')) {
                return response()->json([
                    'error' => [
                        'code' => 'MISSING_CREDENTIALS',
                        'message' => __('Missing required credential: :field', ['field' => $field]),
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($config) {
            Gate::authorize('update', $config);
            $config->update([
                'credentials' => $effectiveCredentials,
                'last_error' => null,
            ]);
        }

        return response()->json([
            'message' => __('Provider configuration is valid.'),
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, string $providerKey): JsonResponse
    {
        if (! $this->providerRegistry->has($providerKey)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('Unknown investment price provider.'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $config = $request->user()
            ->investmentProviderConfigs()
            ->where('provider_key', $providerKey)
            ->first();

        if (! $config) {
            return response()->json([], Response::HTTP_NO_CONTENT);
        }

        Gate::authorize('delete', $config);
        $config->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
