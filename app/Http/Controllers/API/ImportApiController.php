<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportParseRequest;
use App\Models\AccountEntity;
use App\Models\FileImportProfile;
use App\Services\Import\CsvParserService;
use App\Services\Import\ImportDuplicateDetectionService;
use App\Services\Import\ImportNormalizationService;
use App\Services\Import\QifParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ImportApiController extends Controller implements HasMiddleware
{
    public function __construct(
        private QifParserService $qifParserService,
        private CsvParserService $csvParserService,
        private ImportNormalizationService $importNormalizationService,
        private ImportDuplicateDetectionService $importDuplicateDetectionService,
    ) {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    public function parse(ImportParseRequest $request): JsonResponse
    {
        /** @var AccountEntity $accountEntity */
        $accountEntity = AccountEntity::query()->findOrFail((int) $request->input('account_id'));
        Gate::authorize('import.parse', $accountEntity);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => __('A valid import file is required.'),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sourceType = (string) $request->input('source_type');
        $parsedWarnings = [];

        try {
            if ($sourceType === 'csv') {
                $profile = $this->resolveFileImportProfile($request, $accountEntity);

                if (! $profile instanceof FileImportProfile) {
                    return response()->json([
                        'error' => [
                            'code' => 'CSV_PROFILE_REQUIRED',
                            'message' => __('A CSV import profile must be selected or set as the account default.'),
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $parsed = $this->csvParserService->parseFile($file, $profile, $accountEntity->id, (int) $request->user()->id);
                $drafts = $parsed['drafts'];
                $parsedWarnings = $parsed['warnings'];
            } else {
                $qifProfile = $this->resolveQifImportProfile($request);
                if ($qifProfile instanceof FileImportProfile) {
                    $this->qifParserService->applyProfile($qifProfile);
                }

                $parsed = $this->qifParserService->parseFile($file);
                $drafts = $this->importNormalizationService->normalizeQifEntries($parsed['entries'], $accountEntity->id);
                $parsedWarnings = $parsed['warnings'];
            }
        } catch (RuntimeException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'IMPORT_PARSE_FAILED',
                    'message' => __($exception->getMessage()),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $drafts = $this->importNormalizationService->enrichDraftsWithPayeeMatches($request->user(), $drafts);
        $drafts = $this->importDuplicateDetectionService->enrichDrafts($request->user(), $drafts);
        $drafts = $this->importNormalizationService->enrichDraftsWithRelatedAiDocuments($request->user(), $drafts);

        $draftWarningCount = 0;
        foreach ($drafts as $draft) {
            $draftWarningCount += count($draft['warnings']);
        }

        return response()->json([
            'source_type' => $sourceType,
            'account_id' => $accountEntity->id,
            'drafts' => $drafts,
            'warnings' => $parsedWarnings,
            'summary' => [
                'total_entries' => count($drafts),
                'total_drafts' => count($drafts),
                'warning_count' => count($parsedWarnings) + $draftWarningCount,
            ],
        ], Response::HTTP_OK);
    }

    private function resolveQifImportProfile(ImportParseRequest $request): ?FileImportProfile
    {
        $selectedProfileId = $request->input('file_import_profile_id');

        if (! is_numeric($selectedProfileId)) {
            return null;
        }

        $profile = FileImportProfile::query()->findOrFail((int) $selectedProfileId);
        Gate::authorize('view', $profile);

        if ($profile->file_type !== 'qif') {
            abort(422, __('The selected profile is not a QIF profile.'));
        }

        return $profile;
    }

    private function resolveFileImportProfile(ImportParseRequest $request, AccountEntity $accountEntity): ?FileImportProfile
    {
        $selectedProfileId = $request->input('file_import_profile_id');

        if (is_numeric($selectedProfileId)) {
            $profile = FileImportProfile::query()->findOrFail((int) $selectedProfileId);
            Gate::authorize('view', $profile);

            return $profile;
        }

        if (is_int($accountEntity->preferred_file_import_profile_id)) {
            $profile = FileImportProfile::query()->find($accountEntity->preferred_file_import_profile_id);
            if ($profile instanceof FileImportProfile) {
                Gate::authorize('view', $profile);

                return $profile;
            }
        }

        return null;
    }
}
