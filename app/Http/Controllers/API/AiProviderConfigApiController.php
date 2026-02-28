<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiProviderConfigRequest;
use App\Http\Resources\AiProviderConfigResource;
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

    public function show(Request $request): JsonResponse
    {
        /**
         * @get("/api/v1/ai/config")
         * @name("api.v1.ai.config.show")
         * @middlewares("api", "auth:sanctum", "verified")
         */

        // For MVP, we assume one config per user
        // Later, this needs to be converted to a normal show method with config ID and proper ownership checks

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

        // As we have just loaded the config for the user, this check is somewhat redundant, but it's good to be consistent and explicit about authorization
        Gate::authorize('view', $config);

        return response()->json(
            (
            new AiProviderConfigResource($config))->resolve(),
            Response::HTTP_OK
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function store(AiProviderConfigRequest $request): JsonResponse
    {
        /**
         * @post("/api/v1/ai/config")
         * @name("api.v1.ai.config.store")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('create', AiProviderConfig::class);

        /** @var User $user */
        $user = $request->user();

        // At the moment we only support one config per user, so if a config already exists, return an error
        if ($user->aiProviderConfigs()->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'CONFIG_ALREADY_EXISTS',
                    'message' => __('You already have an AI provider configuration. Please update your existing configuration instead.'),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create new config
        $config = AiProviderConfig::create([
            'user_id' => $user->id,
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
            'api_key' => $request->input('api_key'),
            'vision_enabled' => (bool) $request->input('vision_enabled', false),
        ]);

        return response()->json(
            new AiProviderConfigResource($config)->resolve(),
            Response::HTTP_CREATED
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function update(AiProviderConfigRequest $request, AiProviderConfig $aiProviderConfig): JsonResponse
    {
        /**
         * @patch("/api/v1/ai/config/{config}")
         * @name("api.v1.ai.config.update")
         * @middlewares("api", "auth:sanctum", "verified")
         */

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

        return response()->json(
            (new AiProviderConfigResource($aiProviderConfig))->resolve(),
            Response::HTTP_OK
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(AiProviderConfig $aiProviderConfig): JsonResponse
    {
        /**
         * @delete("/api/v1/ai/config/{config}")
         * @name("api.v1.ai.config.destroy")
         * @middlewares("api", "auth:sanctum", "verified")
         */
        Gate::authorize('delete', $aiProviderConfig);

        $aiProviderConfig->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function test(AiProviderConfigRequest $request): JsonResponse
    {
        /**
         * @post("/api/v1/ai/config/test")
         * @name("api.v1.ai.config.test")
         * @middlewares("api", "auth:sanctum", "verified")
         */

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
