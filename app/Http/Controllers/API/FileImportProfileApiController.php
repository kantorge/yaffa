<?php

namespace App\Http\Controllers\API;

use App\Exceptions\AiProviderFailureException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileImportProfileStoreRequest;
use App\Http\Requests\FileImportProfileUpdateRequest;
use App\Http\Requests\SuggestFileImportProfileRequest;
use App\Models\AccountEntity;
use App\Models\AiProviderConfig;
use App\Models\FileImportProfile;
use App\Services\Import\AiImportProfileSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class FileImportProfileApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FileImportProfile::class);

        $user = $request->user();

        $query = FileImportProfile::query()
            ->selectableForUser($user)
            ->with(['accountEntities' => fn ($q) => $q
                ->where('user_id', $user->id)
                ->select(['id', 'name', 'preferred_file_import_profile_id']),
            ])
            ->orderByDesc('type')
            ->orderBy('name');

        $allowedFileTypes = ['csv', 'qif'];
        $fileType = $request->input('file_type');
        if ($request->filled('file_type') && in_array($fileType, $allowedFileTypes, true)) {
            $query->where('file_type', $fileType);
        }

        return response()->json([
            'data' => $query->get(),
        ], Response::HTTP_OK);
    }

    public function store(FileImportProfileStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', FileImportProfile::class);

        $user = $request->user();

        $profile = new FileImportProfile([
            'file_type' => $request->input('file_type', 'csv'),
            'name' => (string) $request->input('name'),
            'delimiter' => (string) $request->input('delimiter', ','),
            'has_header_row' => (bool) $request->input('has_header_row', true),
            'date_format' => $request->input('date_format'),
            'decimal_separator' => $request->input('decimal_separator'),
            'thousand_separator' => $request->input('thousand_separator'),
            'sign_handling' => $request->input('sign_handling'),
            'mapping_json' => (array) $request->input('mapping_json', []),
            'options_json' => (array) $request->input('options_json', []),
            'active' => (bool) $request->input('active', true),
        ]);
        $profile->user_id = $user?->id;
        $profile->type = 'user';
        $profile->save();

        return response()->json(['data' => $profile], Response::HTTP_CREATED);
    }

    public function update(FileImportProfileUpdateRequest $request, FileImportProfile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);

        $profile->fill($request->validated());
        $profile->save();

        return response()->json(['data' => $profile], Response::HTTP_OK);
    }

    public function destroy(FileImportProfile $profile): JsonResponse
    {
        Gate::authorize('delete', $profile);

        if ($profile->accountEntities()->exists()) {
            return response()->json([
                'message' => __('This profile cannot be deleted because it is set as the default for one or more accounts.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function suggest(SuggestFileImportProfileRequest $request, AiImportProfileSuggestionService $service): JsonResponse
    {
        $user = $request->user();

        $aiConfig = AiProviderConfig::query()->where('user_id', $user?->id)->first();
        if (! $aiConfig instanceof AiProviderConfig) {
            return response()->json([
                'message' => 'No AI provider is configured for your account. Please configure an AI provider in your settings before using this feature.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->file('file');
        $csvContent = $file !== null ? (string) file_get_contents((string) $file->getRealPath()) : '';

        if ($csvContent === '') {
            return response()->json([
                'message' => 'The uploaded file could not be read.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $accountId = $request->integer('account_id') ?: null;
        if ($accountId !== null) {
            $account = AccountEntity::query()->find($accountId);
            if ($account instanceof AccountEntity) {
                Gate::authorize('view', $account);
            }
        }

        try {
            $suggestion = $service->suggest(
                config: $aiConfig,
                csvContent: $csvContent,
                accountId: $accountId,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AiProviderFailureException $e) {
            return response()->json([
                'message' => 'The AI provider encountered an error while generating the profile suggestion. Please try again later.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $suggestion], Response::HTTP_OK);
    }

}
