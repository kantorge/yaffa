<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiProviderConfigRequest;
use App\Models\AiProviderConfig;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Prism\Prism\Facades\Prism;
use Symfony\Component\HttpFoundation\Response;
use Log;

class AiProviderConfigApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * GET /api/ai/config - Get user's AI provider config
     */
    public function show(Request $request): JsonResponse
    {
        // For MVP, we assume one config per user
        /** @var User $user */
        $user = $request->user();
        $config = $user->aiProviderConfigs()->first();

        if (! $config) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => __('No AI provider configured yet.'),
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('view', $config);

        // Return config without exposing API key
        return response()->json([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
            'vision_enabled' => $config->vision_enabled,
            'created_at' => $config->created_at,
            'updated_at' => $config->updated_at,
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/ai/config - Create AI provider config
     *
     * @throws AuthorizationException
     */
    public function store(AiProviderConfigRequest $request): JsonResponse
    {
        Gate::authorize('create', AiProviderConfig::class);

        /** @var User $user */
        $user = $request->user();

        // Delete existing config(s) if present (enforce one per user)
        $user->aiProviderConfigs()->delete();

        // Create new config
        $config = AiProviderConfig::create([
            'user_id' => $user->id,
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
            'api_key' => $request->input('api_key'),
            'vision_enabled' => (bool) $request->input('vision_enabled', false),
        ]);

        return response()->json([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
            'vision_enabled' => $config->vision_enabled,
            'message' => __('AI provider configured successfully'),
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/ai/config/{id} - Update AI provider config
     *
     * @throws AuthorizationException
     */
    public function update(AiProviderConfigRequest $request, AiProviderConfig $aiProviderConfig): JsonResponse
    {
        Gate::authorize('update', $aiProviderConfig);

        $validated = $request->validated();

        // Prepare update data
        $updateData = [
            'provider' => $validated['provider'],
            'model' => $validated['model'],
        ];

        if (array_key_exists('vision_enabled', $validated)) {
            $updateData['vision_enabled'] = $validated['vision_enabled'];
        }

        // Only update API key if provided (and not the empty string)
        if (!empty($validated['api_key']) && $validated['api_key'] !== '__existing__') {
            $updateData['api_key'] = $validated['api_key'];
        }

        $aiProviderConfig->update($updateData);

        return response()->json([
            'id' => $aiProviderConfig->id,
            'provider' => $aiProviderConfig->provider,
            'model' => $aiProviderConfig->model,
            'vision_enabled' => $aiProviderConfig->vision_enabled,
            'updated_at' => $aiProviderConfig->updated_at,
        ], Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(AiProviderConfig $aiProviderConfig): JsonResponse
    {
        /**
         * @delete("/api/ai/config/{config}")
         * @name("api.ai.config.destroy")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('delete', $aiProviderConfig);

        $aiProviderConfig->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/ai/test - Test AI provider connection
     */
    public function test(AiProviderConfigRequest $request): JsonResponse
    {
        // Basic validation is already done by AiProviderConfigRequest
        $validated = $request->validated();

        // If the API key is indicated as existing, fetch the stored key
        if ($validated['api_key'] === '__existing__') {
            /** @var User $user */
            $user = $request->user();
            $existingConfig = $user->aiProviderConfigs()->first();
            if (!$existingConfig) {
                return response()->json([
                    'error' => [
                        'code' => 'CONFIG_NOT_FOUND',
                        'message' => __('No existing AI provider configuration found'),
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }
            $validated['api_key'] = $existingConfig->api_key;
        }

        try {
            // Try a simple completion to test the connection
            $response = Prism::text()
                ->using($validated['provider'], $validated['model'])
                ->usingProviderConfig([
                    'api_key' => $validated['api_key'],
                ])
                ->withPrompt('Hello, this is a test. Reply with "OK".')
                ->asText();

            if ($response->text) {
                return response()->json([
                    'message' => __('Connection successful'),
                ], Response::HTTP_OK);
            }

            return response()->json([
                'error' => [
                    'code' => 'CONNECTION_FAILED',
                    'message' => __('No response from AI provider'),
                ],
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            Log::error("AI provider test failed: {$e->getMessage()}");

            return response()->json([
                'error' => [
                    'code' => 'CONNECTION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
