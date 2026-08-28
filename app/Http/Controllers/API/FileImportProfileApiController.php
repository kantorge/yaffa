<?php

namespace App\Http\Controllers\API;

use App\Exceptions\AiProviderFailureException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileImportProfileRequest;
use App\Http\Requests\SuggestFileImportProfileRequest;
use App\Models\AccountEntity;
use App\Models\AiProviderConfig;
use App\Models\FileImportProfile;
use App\Services\Import\AiImportProfileSuggestionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
                'index',
            ])]
#[Middleware('abilities:write', only: [
                'store', 'update', 'destroy', 'suggest',
            ])]
class FileImportProfileApiController extends Controller
{
    /**
     * List file import profiles
     *
     * Returns file import profiles selectable by the current user, optionally filtered
     * by file type (csv or qif).
     *
     * @throws AuthorizationException
     */
    #[Authorize('viewAny', FileImportProfile::class)]
    public function index(Request $request): JsonResponse
    {
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

    /**
     * Create a file import profile
     *
     * @throws AuthorizationException
     */
    #[Authorize('create', FileImportProfile::class)]
    public function store(FileImportProfileRequest $request): JsonResponse
    {
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

    /**
     * Update a file import profile
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'profile')]
    public function update(FileImportProfileRequest $request, FileImportProfile $profile): JsonResponse
    {
        $profile->fill($request->validated());
        $profile->save();

        return response()->json(['data' => $profile], Response::HTTP_OK);
    }

    /**
     * Delete a file import profile
     *
     * Fails if the profile is set as the default import profile for one or more accounts.
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'profile')]
    public function destroy(FileImportProfile $profile): JsonResponse
    {
        if ($profile->accountEntities()->exists()) {
            return response()->json([
                'message' => __('This profile cannot be deleted because it is set as the default for one or more accounts.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $profile->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Suggest a file import profile
     *
     * Uses the user's configured AI provider to analyze an uploaded CSV file and suggest
     * a matching file import profile (delimiter, header, column mapping, etc.).
     *
     * @throws AuthorizationException
     */
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
