<?php

namespace App\Http\Controllers\API;

use App\Exceptions\PriceProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvestmentProviderConfigRequest;
use App\Http\Resources\InvestmentProviderConfigResource;
use App\Models\InvestmentProviderConfig;
use App\Services\InvestmentPriceProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:settings', only: [
                'index', 'show', 'update', 'test', 'destroy',
            ])]
class InvestmentProviderConfigApiController extends Controller
{
    public function __construct(private InvestmentPriceProviderRegistry $providerRegistry) {}

    /**
     * List investment provider configs
     */
    #[Authorize('viewAny', InvestmentProviderConfig::class)]
    public function index(Request $request): JsonResponse
    {
        $configs = $request->user()
            ->investmentProviderConfigs()
            ->orderBy('provider_key')
            ->get();

        return response()->json(
            InvestmentProviderConfigResource::collection($configs)->resolve(),
            Response::HTTP_OK
        );
    }

    /**
     * Get an investment provider config
     */
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

    /**
     * Save an investment provider config
     *
     * Creates the configuration for a provider if none exists yet, or updates
     * it otherwise. Incoming credentials are merged with previously stored
     * ones so omitted fields are not accidentally cleared.
     */
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
            'options' => $validated['options'] ?? $existing?->options,
            'rate_limit_overrides' => $validated['rate_limit_overrides'] ?? $existing?->rate_limit_overrides,
            'credentials' => array_merge($previousCredentials, $incomingCredentials),
        ];

        if (! $existing) {
            $config = InvestmentProviderConfig::create([
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

    /**
     * Test an investment provider config
     *
     * Validates the effective credentials (stored plus any provided in the
     * request) against the provider, optionally persisting them if valid.
     */
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

        $incomingCredentials = array_filter($incomingCredentials, fn ($value) => $value !== null);

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
        }

        $persist = $request->boolean('persist', false);

        try {
            $provider = $this->providerRegistry->get($providerKey);
            $provider->validateCredentials($effectiveCredentials);

            if ($persist && $config) {
                $config->update([
                    'credentials' => $effectiveCredentials,
                    'last_error' => null,
                ]);
            }
        } catch (PriceProviderException $exception) {
            $errorMessage = $exception->errorMessage;

            if ($persist && $config) {
                $config->update([
                    'credentials' => $effectiveCredentials,
                    'last_error' => $errorMessage,
                ]);
            }

            return response()->json([
                'error' => [
                    'code' => 'CREDENTIAL_VALIDATION_FAILED',
                    'message' => $errorMessage,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'message' => __('Provider configuration is valid.'),
        ], Response::HTTP_OK);
    }

    /**
     * Delete an investment provider config
     */
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
