<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportParseRequest;
use App\Models\AccountEntity;
use App\Services\Import\ImportNormalizationService;
use App\Services\Import\QifParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ImportApiController extends Controller implements HasMiddleware
{
    public function __construct(
        private QifParserService $qifParserService,
        private ImportNormalizationService $importNormalizationService,
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

        $parsed = $this->qifParserService->parseFile($file);
        $drafts = $this->importNormalizationService->normalizeQifEntries($parsed['entries'], $accountEntity->id);

        $draftWarningCount = 0;
        foreach ($drafts as $draft) {
            $draftWarningCount += count($draft['warnings']);
        }

        return response()->json([
            'source_type' => 'qif',
            'account_id' => $accountEntity->id,
            'drafts' => $drafts,
            'warnings' => $parsed['warnings'],
            'summary' => [
                'total_entries' => count($parsed['entries']),
                'total_drafts' => count($drafts),
                'warning_count' => count($parsed['warnings']) + $draftWarningCount,
            ],
        ], Response::HTTP_OK);
    }
}
