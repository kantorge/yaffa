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
use Prism\Prism\Enums\Provider;
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
        $user = $request->user();
        $config = $user->aiProviderConfig;

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

        // Delete existing config if present (enforce one per user)
        $user->aiProviderConfig?->delete();

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
    public function update(AiProviderConfigRequest $request, AiProviderConfig $config): JsonResponse
    {
        Gate::authorize('update', $config);

        $config->update([
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
            'api_key' => $request->input('api_key'),
        ]);

        return response()->json([
            'id' => $config->id,
            'provider' => $config->provider,
            'model' => $config->model,
            'updated_at' => $config->updated_at,
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/ai/config/{id} - Delete AI provider config
     *
     * @throws AuthorizationException
     */
    public function destroy(AiProviderConfig $config): JsonResponse
    {
        Gate::authorize('delete', $config);

        $config->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/ai/test - Test AI provider connection
     */
    public function test(AiProviderConfigRequest $request): JsonResponse
    {
        // Basic validation is already done by AiProviderConfigRequest
        $validated = $request->validated();

        try {
            $provider = Prism::provider($validated['provider']);

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
