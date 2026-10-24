<?php

namespace App\Http\Controllers\API;

use App\Enums\AiDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiDocumentRequest;
use App\Http\Requests\UpdateAiDocumentRequest;
use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\TransactionOrigin;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Arr;
use App\Services\Import\ImportDuplicateDetectionService;
use App\Services\Import\ImportNormalizationService;
use App\Services\SameEventClassifier;
use App\Services\TransactionDraftService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: [
    'index', 'show', 'summary',
])]
#[Middleware('abilities:write', only: [
    'store', 'update', 'reprocess', 'reprocessOpen', 'dismiss', 'checkDuplicates', 'destroy',
])]
#[Middleware('abilities:settings', only: [
    'cleanupOldFiles',
])]
class AiDocumentApiController extends Controller
{
    public function __construct(
        private TransactionDraftService $transactionDraftService,
    ) {
    }

    /**
     * Upload a document for AI processing
     *
     * Accepts uploaded files and/or text input, creates an AI document, and queues it for background processing.
     *
     * @throws AuthorizationException
     */
    #[Authorize('create', AiDocument::class)]
    public function store(StoreAiDocumentRequest $request): JsonResponse
    {
        // Create the document
        $document = AiDocument::create([
            'status' => 'ready_for_processing',
            'source_type' => 'manual_upload',
            'custom_prompt' => $request->input('custom_prompt'),
        ]);

        try {
            $fileHashes = [];

            // Store uploaded files
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $fileHashes[] = $this->storeFile($document, $file);
                }
            }

            // Store text input if provided
            if ($request->input('text_input')) {
                $fileHashes[] = $this->storeTextFile($document, $request->input('text_input'));
            }

            $document->update(['content_hash' => AiDocument::hashFiles($fileHashes)]);
        } catch (Throwable $e) {
            // Don't leave a half-stored document (or its files) behind; cascades to the file records.
            Storage::disk('local')->deleteDirectory("ai_documents/{$document->user_id}/{$document->id}");
            $document->delete();

            throw $e;
        }

        // Dispatch processing job
        AiProcessingJob::dispatch($document);

        return response()->json([
            'id' => $document->id,
            'status' => $document->status,
            'message' => __('Document uploaded and queued for processing'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a document
     *
     * Updates the custom prompt or status of an AI document. Setting the status (back to
     * ready_for_processing) is only allowed from ready_for_review or processing_failed.
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'aiDocument')]
    public function update(UpdateAiDocumentRequest $request, AiDocument $aiDocument): JsonResponse
    {
        $aiDocument = DB::transaction(function () use ($request, $aiDocument): ?AiDocument {
            $document = $request->filled('status') ? $this->lockReprocessable($aiDocument) : $aiDocument;

            if (! $document) {
                return null;
            }

            if ($request->filled('custom_prompt')) {
                $document->custom_prompt = $request->input('custom_prompt');
            }

            if ($request->filled('status')) {
                $document->transitionTo(AiDocumentStatus::from($request->input('status')));
            } else {
                $document->save();
            }

            return $document;
        });

        if (! $aiDocument) {
            return response()->json([
                'error' => __('Document cannot be reprocessed from current status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'id' => $aiDocument->id,
            'status' => $aiDocument->status,
            'custom_prompt' => $aiDocument->custom_prompt,
        ], Response::HTTP_OK);
    }

    /**
     * Get document processing summary
     *
     * Returns aggregate counts (ready for review, processing failed, oldest pending) for the
     * user's AI documents that are not in a terminal status.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasProviderConfig = $user->aiProviderConfigs()->exists();

        if (! $hasProviderConfig) {
            return response()->json(['active_provider' => false], Response::HTTP_OK);
        }

        $stats = AiDocument::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', AiDocumentStatus::values(AiDocumentStatus::terminal()))
            ->selectRaw(
                "COUNT(*) as total, "
                . "COUNT(CASE WHEN status = 'ready_for_review' THEN 1 END) as ready_for_review, "
                . "COUNT(CASE WHEN status = 'processing_failed' THEN 1 END) as processing_failed, "
                . "MIN(created_at) as oldest_created_at"
            )
            ->toBase()
            ->first();

        return response()->json([
            'active_provider' => true,
            'total' => (int) ($stats->total ?? 0),
            'ready_for_review' => (int) ($stats->ready_for_review ?? 0),
            'processing_failed' => (int) ($stats->processing_failed ?? 0),
            'auto_recorded_to_review' => TransactionOrigin::query()
                ->where('user_id', $user->id)
                ->where('relation', TransactionOrigin::RELATION_CREATED)
                ->whereNull('reviewed_at')
                ->count(),
            'oldest_created_at' => $stats->oldest_created_at
                ? Carbon::parse((string) $stats->oldest_created_at)->toIso8601String()
                : null,
        ], Response::HTTP_OK);
    }

    /**
     * Cleanup old AI documents
     *
     * Queues the retention cleanup for the authenticated user. It deletes finalized AI documents older
     * than the user's retention period (`document_retention_days`), together with their stored files and
     * received emails, and emails a reminder about older documents that are not finalized. The
     * transactions created from the documents are kept. Does nothing if no retention period is set.
     */
    public function cleanupOldFiles(Request $request): JsonResponse
    {
        Artisan::queue('ai-documents:cleanup-old-files', [
            'userId' => $request->user()->id,
        ]);

        return response()->json([
            'message' => __('maintenance.aiDocumentOldFiles.queued'),
        ], Response::HTTP_OK);
    }

    /**
     * List documents
     *
     * Returns the user's AI documents, with optional filters for date range, status, source
     * type, and free-text search.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only what the document list displays: no chat history or prompt, no mail body, no transaction details
        $query = AiDocument::query()
            ->select([
                'id', 'user_id', 'status', 'source_type', 'processed_transaction_data', 'received_mail_id',
                'content_hash', 'document_kind', 'processed_at', 'status_changed_at', 'created_at', 'updated_at',
            ])
            ->where('user_id', $user->id)
            ->with([
                'aiDocumentFiles:id,ai_document_id,file_name',
                'receivedMail:id,subject',
                'transaction:id,ai_document_id',
                'origins:id,origin_type,origin_id,transaction_id,relation,reviewed_at',
            ]);

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse((string) $request->input('date_from'))->startOfDay();
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse((string) $request->input('date_to'))->endOfDay();
            $query->where('created_at', '<=', $dateTo);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by source_type
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        // Search by content (optional)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereJsonContains('processed_transaction_data', $search)
                    ->orWhere('custom_prompt', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $documents = $query->latest()->paginate($perPage);

        $this->enrichMatchedEntities($documents->getCollection(), $user);

        $documents->getCollection()->each(
            fn (AiDocument $document) => $document->transaction?->makeHidden('transaction_currency')
        );

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'total' => $documents->total(),
                'per_page' => $documents->perPage(),
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
            ],
            'links' => [
                'first' => $documents->url(1),
                'last' => $documents->url($documents->lastPage()),
                'next' => $documents->nextPageUrl(),
                'prev' => $documents->previousPageUrl(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get a document
     *
     * Returns full details for a single AI document, including matched entities and related records.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'aiDocument')]
    public function show(Request $request, AiDocument $aiDocument): JsonResponse
    {
        $aiDocument->load('aiDocumentFiles', 'receivedMail', 'transaction', 'origins');
        $this->enrichProcessedData($aiDocument, $request->user());

        return response()->json([
            'document' => $aiDocument
        ], Response::HTTP_OK);
    }

    /**
     * Reprocess a document
     *
     * Resets a document to ready_for_processing and re-queues it for AI processing. Only
     * allowed from ready_for_review or processing_failed; a finalized document already created a transaction.
     *
     * @throws AuthorizationException
     */
    #[Authorize('reprocess', 'aiDocument')]
    public function reprocess(AiDocument $aiDocument): JsonResponse
    {
        $aiDocument = DB::transaction(function () use ($aiDocument): ?AiDocument {
            $document = $this->lockReprocessable($aiDocument);

            if (! $document) {
                return null;
            }

            // Reset document to ready_for_processing
            $document->processed_transaction_data = null;
            $document->ai_chat_history = null;
            $document->document_kind = null;
            $document->processed_at = null;
            $document->transitionTo(AiDocumentStatus::ReadyForProcessing);

            return $document;
        });

        if (! $aiDocument) {
            return response()->json([
                'error' => __('Document cannot be reprocessed from current status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Dispatch processing job (after the commit, so the job sees the reset document)
        AiProcessingJob::dispatch($aiDocument);

        return response()->json([
            'status' => $aiDocument->status,
            'message' => __('Document reprocessing queued'),
        ], Response::HTTP_OK);
    }

    /**
     * Reprocess all open documents
     *
     * Sends every one of the user's open (ready for review or processing failed) documents back to
     * ready_for_processing in a single query. No jobs are dispatched here: the scheduled
     * app:process-ai-documents command picks them up in batches, which also keeps a large backlog from
     * flooding the queue and respects the user's AI enabled setting.
     */
    #[Authorize('create', AiDocument::class)]
    public function reprocessOpen(Request $request): JsonResponse
    {
        $count = AiDocument::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', AiDocumentStatus::values([
                AiDocumentStatus::ReadyForReview,
                AiDocumentStatus::ProcessingFailed,
            ]))
            ->update([
                'processed_transaction_data' => null,
                'ai_chat_history' => null,
                'document_kind' => null,
                'processed_at' => null,
                'status' => AiDocumentStatus::ReadyForProcessing->value,
                'status_changed_at' => now(),
            ]);

        return response()->json([
            'count' => $count,
            'message' => trans_choice('{0} No open documents to reprocess|{1} :count document queued for reprocessing|[2,*] :count documents queued for reprocessing', $count, ['count' => $count]),
        ], Response::HTTP_OK);
    }

    /**
     * Dismiss a document
     *
     * Closes a document the user does not want to record, for example because it is insufficient. Only
     * allowed from ready_for_review or processing_failed. A dismissed document can still be reprocessed.
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'aiDocument')]
    public function dismiss(AiDocument $aiDocument): JsonResponse
    {
        $dismissed = DB::transaction(function () use ($aiDocument): bool {
            $document = AiDocument::query()->whereKey($aiDocument->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($document->status, [AiDocumentStatus::ReadyForReview->value, AiDocumentStatus::ProcessingFailed->value], true)) {
                return false;
            }

            $document->transitionTo(AiDocumentStatus::Dismissed);

            return true;
        });

        if (! $dismissed) {
            return response()->json([
                'error' => __('Document cannot be dismissed from current status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'status' => AiDocumentStatus::Dismissed->value,
            'message' => __('Document dismissed'),
        ], Response::HTTP_OK);
    }

    /**
     * Delete a document
     *
     * Deletes an AI document, its stored files, and any linked received mail.
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'aiDocument')]
    public function destroy(AiDocument $aiDocument): JsonResponse
    {
        // Delete stored files
        foreach ($aiDocument->aiDocumentFiles as $file) {
            /** @var AiDocumentFile $file */
            Storage::disk('local')->delete($file->file_path);
        }

        // If linked to a received mail, delete it as well
        if ($aiDocument->receivedMail) {
            $aiDocument->receivedMail->delete();
        }

        $aiDocument->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Check a document for duplicate transactions
     *
     * Compares an AI-processed document's extracted transaction data against existing
     * transactions to find likely duplicates.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'aiDocument')]
    public function checkDuplicates(
        AiDocument $aiDocument,
        DuplicateDetectionService $duplicateService,
        SameEventClassifier $classifier,
        ImportDuplicateDetectionService $importDuplicateService,
        ImportNormalizationService $normalizationService,
    ): JsonResponse {
        // Asking for duplicates of an unprocessed document is not valid
        if (! $aiDocument->processed_transaction_data) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        $extractedData = $duplicateService->matchDataFromDraft($aiDocument->processed_transaction_data);

        if (! isset($extractedData['date'])) {
            return response()->json([
                'duplicates' => [],
                'schedules' => [],
                'related_documents' => [],
                'documents' => [],
            ], Response::HTTP_OK);
        }

        /** @var User $user */
        $user = $aiDocument->user;

        $duplicates = $duplicateService->findDuplicates($user, $extractedData);

        // The document's data in the shape of an import draft, so the import's matching can be reused
        $payload = $aiDocument->processed_transaction_data;
        $payeeAccountId = ($extractedData['transaction_type'] ?? null) === 'deposit'
            ? ($extractedData['account_from_id'] ?? null)
            : ($extractedData['account_to_id'] ?? null);
        $draft = [
            'date' => $extractedData['date'],
            'amount' => $extractedData['amount'] ?? null,
            'config_type' => $extractedData['config_type'] ?? 'standard',
            'transaction_type' => $extractedData['transaction_type'] ?? null,
            'config' => Arr::only($extractedData, ['account_from_id', 'account_to_id']),
            'payee' => data_get($payload, 'raw.payee'),
            'matched_payee' => $payeeAccountId !== null ? ['id' => $payeeAccountId] : null,
        ];

        // Load full transaction details for frontend
        $transactionIds = array_column($duplicates, 'id');
        $transactions = \App\Models\Transaction::whereIn('id', $transactionIds)
            ->with(['config', 'transactionItems'])
            ->get()
            ->keyBy('id');

        $enrichedDuplicates = array_map(function ($duplicate) use ($transactions, $importDuplicateService, $draft) {
            $transaction = $transactions->get($duplicate['id']);

            return [
                'id' => $duplicate['id'],
                'similarity' => $duplicate['similarity'],
                'date' => $transaction->date,
                // Emitted as a decimal string (matching the wire format Eloquent's
                // SerializesCastableAttributes gives this field elsewhere), since this is a
                // raw array embedding the Money value directly, bypassing that control point.
                'amount' => $transaction->cashflow_value !== null ? (string) $transaction->cashflow_value->getAmount() : null,
                'type' => $transaction->config_type,
                'comment' => $transaction->comment,
                'matched_on' => $importDuplicateService->buildMatchedOnSignals($draft, $transaction),
            ];
        }, $duplicates);

        // Active schedules whose next occurrence could be entered instead of adding a new transaction,
        // found by the same engine as the CSV/QIF import
        $schedules = $importDuplicateService->enrichDraftsWithScheduleCandidates($user, [$draft])[0]['schedule_candidates'];

        // Other open documents describing the same purchase
        $key = $classifier->keyFromDocument($aiDocument);
        $documents = $key === null
            ? []
            : $classifier->openDocumentMatches($user, $key, $aiDocument->id)
                ->map(fn (AiDocument $other) => [
                    'id' => $other->id,
                    'status' => $other->status,
                    'url' => route('ai-documents.show', $other),
                ])
                ->values();

        // Other documents that may describe the same purchase, found as in the CSV/QIF import. Documents
        // already listed above are not repeated, and the document itself is not its own match.
        $listedIds = collect($documents)->pluck('id')->push($aiDocument->id);
        $relatedDocuments = collect($normalizationService->enrichDraftsWithRelatedAiDocuments($user, [$draft])[0]['related_ai_documents'])
            ->reject(fn (array $candidate) => $listedIds->contains($candidate['ai_document_id']))
            ->values();

        return response()->json([
            'duplicates' => $enrichedDuplicates,
            'schedules' => $schedules,
            'related_documents' => $relatedDocuments,
            'documents' => $documents,
        ], Response::HTTP_OK);
    }

    /**
     * Store an uploaded file for the document
     *
     * @return string sha256 of the file's content
     */
    private function storeFile(AiDocument $aiDocument, $file): string
    {
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileType = $this->getFileType($extension);

        // Store file
        $path = $file->storeAs(
            "ai_documents/{$aiDocument->user_id}/{$aiDocument->id}",
            $filename,
            'local'
        );

        if ($path === false) {
            throw new RuntimeException("Failed to store uploaded file {$filename}.");
        }

        // Create database record
        AiDocumentFile::create([
            'ai_document_id' => $aiDocument->id,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => $fileType,
        ]);

        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Store text input as a file
     */
    private function storeTextFile(AiDocument $aiDocument, string $textInput): string
    {
        $filename = 'text_input_' . now()->timestamp . '.txt';

        // Storage::put() returns a bool, not the path
        $path = "ai_documents/{$aiDocument->user_id}/{$aiDocument->id}/{$filename}";

        if (! Storage::disk('local')->put($path, $textInput)) {
            throw new RuntimeException("Failed to store text input {$filename}.");
        }

        AiDocumentFile::create([
            'ai_document_id' => $aiDocument->id,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => 'txt',
        ]);

        return hash('sha256', $textInput);
    }

    /**
     * Re-read the document with a row lock and return it only if it can still be reprocessed. Must be
     * called inside a DB transaction, so the status cannot change between this check and the reset.
     */
    private function lockReprocessable(AiDocument $aiDocument): ?AiDocument
    {
        $document = AiDocument::query()->whereKey($aiDocument->getKey())->lockForUpdate()->firstOrFail();

        return AiDocumentStatus::isReprocessable($document->status) ? $document : null;
    }

    /**
     * Get file type from extension
     */
    private function getFileType(string $extension): string
    {
        $extension = mb_strtolower($extension);
        $allowedTypes = config('ai-documents.file_upload.allowed_types');

        // Normalize some common extensions
        if ($extension === 'jpeg') {
            return 'jpg';
        }

        if (in_array($extension, $allowedTypes, true)) {
            return $extension;
        }

        return 'txt';
    }

    /**
     * Adds `matched_entities` to the drafts of a page of documents with one query per entity kind.
     *
     * @param  Collection<int, AiDocument>  $documents
     */
    private function enrichMatchedEntities(Collection $documents, User $user): void
    {
        $drafts = $documents
            ->filter(fn (AiDocument $document): bool => (bool) $document->processed_transaction_data)
            ->map(fn (AiDocument $document): array => $document->processed_transaction_data)
            ->all();

        $enriched = $this->transactionDraftService->enrichMatchedEntities($drafts, $user);

        foreach ($enriched as $key => $draft) {
            $documents[$key]->processed_transaction_data = $draft;
        }
    }

    private function enrichProcessedData(AiDocument $aiDocument, User $user): void
    {
        if ($aiDocument->processed_transaction_data) {
            $aiDocument->processed_transaction_data = $this->transactionDraftService->enrich($aiDocument->processed_transaction_data, $user);
        }
    }
}
