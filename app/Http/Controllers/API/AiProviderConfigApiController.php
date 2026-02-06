<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiProviderConfigRequest;
use App\Models\AiProviderConfig;
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
            ['auth:sanctum', 'verified'],
        ];
    }

    /**
     * GET /api/ai/config - Get user's AI provider config
     */
    public function show(Request $request): JsonResponse
    {
        // For MVP, we assume one config per user
        $user = $request->user();
        $config = $user->aiProviderConfigs()->first();

        if (! $config) {
            return response()->json([
                'error' => __('No AI provider configured'),
            ], Response::HTTP_NOT_FOUND);
        }

        Gate::authorize('view', $config);

        // Return config without exposing API key
        return response()->json([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
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

        $user = $request->user();

        // Delete existing config(s) if present (enforce one per user)
        $user->aiProviderConfigs()->delete();

        // Create new config
        $config = AiProviderConfig::create([
            'user_id' => $user->id,
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
            'api_key' => $request->input('api_key'),
        ]);

        return response()->json([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
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

        // Only update API key if provided (and not the empty string)
        if (!empty($validated['api_key']) && $validated['api_key'] !== '__existing__') {
            $updateData['api_key'] = $validated['api_key'];
        }

        $aiProviderConfig->update($updateData);

        return response()->json([
            'id' => $aiProviderConfig->id,
            'provider' => $aiProviderConfig->provider,
            'model' => $aiProviderConfig->model,
            'updated_at' => $aiProviderConfig->updated_at,
        ], Response::HTTP_OK);
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(AiProviderConfig $aiProviderConfig): JsonResponse
    {
        /**
         * @delete('/api/ai/config/{config}')
         * @name('api.ai.config.destroy')
         * @middlewares('api', 'auth:sanctum', 'verified')
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
            $user = $request->user();
            $existingConfig = $user->aiProviderConfigs()->first();
            if (!$existingConfig) {
                return response()->json([
                    'message' => __('No existing AI provider configuration found'),
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

            if ($response && $response->text) {
                return response()->json([
                    'message' => __('Connection successful'),
                ], Response::HTTP_OK);
            }

            return response()->json([
                'message' => __('No response from AI provider'),
            ], Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            Log::error("AI provider test failed: {$e->getMessage()}");

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
