<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiDocumentRequest;
use App\Http\Requests\UpdateAiDocumentRequest;
use App\Jobs\AiProcessingJob;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\Category;
use App\Models\Investment;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AiDocumentApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * POST /api/documents - Upload and create a new AI document
     *
     * @throws AuthorizationException
     */
    public function store(StoreAiDocumentRequest $request): JsonResponse
    {
        Gate::authorize('create', AiDocument::class);

        $user = $request->user();

        // Create the document
        $document = AiDocument::create([
            'user_id' => $user->id,
            'status' => 'ready_for_processing',
            'source_type' => 'manual_upload',
            'custom_prompt' => $request->input('custom_prompt'),
        ]);

        // Store uploaded files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $this->storeFile($document, $file);
            }
        }

        // Store text input if provided
        if ($request->input('text_input')) {
            $this->storeTextFile($document, $request->input('text_input'));
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
     * PATCH /api/documents/{id} - Update document (custom prompt or status)
     *
     * @throws AuthorizationException
     */
    public function update(UpdateAiDocumentRequest $request, AiDocument $aiDocument): JsonResponse
    {
        Gate::authorize('update', $aiDocument);

        if ($request->filled('custom_prompt')) {
            $aiDocument->custom_prompt = $request->input('custom_prompt');
        }

        if ($request->filled('status')) {
            $aiDocument->status = $request->input('status');
        }

        $aiDocument->save();

        return response()->json([
            'id' => $aiDocument->id,
            'status' => $aiDocument->status,
            'custom_prompt' => $aiDocument->custom_prompt,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/documents - List user's documents with filters
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AiDocument::query()
            ->where('user_id', $user->id)
            ->with(['aiDocumentFiles', 'receivedMail', 'transaction']);

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
     * GET /api/documents/{id} - Get document details
     *
     * @throws AuthorizationException
     */
    public function show(AiDocument $aiDocument): JsonResponse
    {
        Gate::authorize('view', $aiDocument);

        $aiDocument->load('aiDocumentFiles', 'receivedMail', 'transaction');
        $this->enrichProcessedData($aiDocument);

        return response()->json([
            'document' => $aiDocument
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/documents/{id}/reprocess - Trigger document reprocessing
     *
     * @throws AuthorizationException
     */
    public function reprocess(AiDocument $aiDocument): JsonResponse
    {
        Gate::authorize('reprocess', $aiDocument);

        // Only allow reprocessing if document is in a terminal or failed state
        if (! in_array($aiDocument->status, ['ready_for_review', 'processing_failed', 'finalized'])) {
            return response()->json([
                'error' => __('Document cannot be reprocessed from current status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Reset document to ready_for_processing
        $aiDocument->status = 'ready_for_processing';
        $aiDocument->processed_transaction_data = null;
        $aiDocument->ai_chat_history = null;
        $aiDocument->processed_at = null;
        $aiDocument->save();

        // Dispatch processing job
        AiProcessingJob::dispatch($aiDocument);

        return response()->json([
            'status' => $aiDocument->status,
            'message' => __('Document reprocessing queued'),
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/documents/{id} - Delete a document and its files
     *
     * @throws AuthorizationException
     */
    public function destroy(AiDocument $aiDocument): JsonResponse
    {
        Gate::authorize('delete', $aiDocument);

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
     * POST /api/documents/{id}/check-duplicates - Check for duplicate transactions
     *
     * @throws AuthorizationException
     */
    public function checkDuplicates(AiDocument $aiDocument, DuplicateDetectionService $duplicateService): JsonResponse
    {
        Gate::authorize('view', $aiDocument);

        // Asking for duplicates of an unprocessed document is not valid
        if (! $aiDocument->processed_transaction_data) {
            return response()->json([], Response::HTTP_BAD_REQUEST);
        }

        $processedData = $aiDocument->processed_transaction_data;
        $extractedData = $processedData['raw'] ?? [];

        if (! is_array($extractedData) || ! array_key_exists('date', $extractedData)) {
            return response()->json([
                'duplicates' => [],
            ], Response::HTTP_OK);
        }

        /** @var \App\Models\User $user */
        $user = $aiDocument->user;

        $duplicates = $duplicateService->findDuplicates($user, $extractedData);

        // Load full transaction details for frontend
        $transactionIds = array_column($duplicates, 'id');
        $transactions = \App\Models\Transaction::whereIn('id', $transactionIds)
            ->get()
            ->keyBy('id');

        $enrichedDuplicates = array_map(function ($duplicate) use ($transactions) {
            $transaction = $transactions->get($duplicate['id']);

            return [
                'id' => $duplicate['id'],
                'similarity' => $duplicate['similarity'],
                'date' => $transaction->date,
                'amount' => $transaction->cashflow_value,
                'type' => $transaction->config_type,
            ];
        }, $duplicates);

        return response()->json([
            'duplicates' => $enrichedDuplicates,
        ], Response::HTTP_OK);
    }

    /**
     * Store an uploaded file for the document
     */
    private function storeFile(AiDocument $aiDocument, $file): void
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

        // Create database record
        AiDocumentFile::create([
            'ai_document_id' => $aiDocument->id,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => $fileType,
        ]);
    }

    /**
     * Store text input as a file
     */
    private function storeTextFile(AiDocument $aiDocument, string $textInput): void
    {
        $filename = 'text_input_' . now()->timestamp . '.txt';

        $path = Storage::disk('local')->put(
            "ai_documents/{$aiDocument->user_id}/{$aiDocument->id}/{$filename}",
            $textInput
        );

        AiDocumentFile::create([
            'ai_document_id' => $aiDocument->id,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => 'txt',
        ]);
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

    private function enrichProcessedData(AiDocument $aiDocument): void
    {
        $processedData = $aiDocument->processed_transaction_data;

        if (! $processedData || ! is_array($processedData)) {
            return;
        }

        if (isset($processedData['transaction_items']) && is_array($processedData['transaction_items'])) {
            $categoryIds = collect($processedData['transaction_items'])
                ->map(fn ($item) => $item['recommended_category_id'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (! empty($categoryIds)) {
                $categories = Category::query()
                    ->with('parent')
                    ->whereIn('id', $categoryIds)
                    ->where('user_id', $aiDocument->user_id)
                    ->get()
                    ->keyBy('id');

                foreach ($processedData['transaction_items'] as &$item) {
                    if (isset($item['recommended_category_id']) && $categories->has($item['recommended_category_id'])) {
                        $recommendedCategory = $categories->get($item['recommended_category_id']);
                        $item['recommended_category_full_name'] = $recommendedCategory->full_name;
                    }
                }
            }
        }

        $config = $processedData['config'] ?? [];
        $transactionType = $processedData['transaction_type'] ?? null;

        $accountIds = collect([
            $config['account_id'] ?? null,
            $config['account_from_id'] ?? null,
            $config['account_to_id'] ?? null,
        ])->filter()->unique()->values()->all();

        $accountsById = AccountEntity::query()
            ->where('user_id', $aiDocument->user_id)
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $investmentIds = collect([
            $config['investment_id'] ?? null,
        ])->filter()->unique()->values()->all();

        $investmentsById = Investment::query()
            ->where('user_id', $aiDocument->user_id)
            ->whereIn('id', $investmentIds)
            ->get()
            ->keyBy('id');

        $matchedEntities = [];

        if ($transactionType === 'transfer') {
            $from = $accountsById->get($config['account_from_id'] ?? null);
            $to = $accountsById->get($config['account_to_id'] ?? null);

            if ($from) {
                $matchedEntities['account_from'] = [
                    'id' => $from->id,
                    'name' => $from->name,
                    'matched' => true,
                    'url' => route('account-entity.show', $from->id),
                ];
            }

            if ($to) {
                $matchedEntities['account_to'] = [
                    'id' => $to->id,
                    'name' => $to->name,
                    'matched' => true,
                    'url' => route('account-entity.show', $to->id),
                ];
            }
        } elseif (in_array($transactionType, ['withdrawal', 'deposit'], true)) {
            $accountId = $transactionType === 'withdrawal'
                ? ($config['account_from_id'] ?? null)
                : ($config['account_to_id'] ?? null);
            $payeeId = $transactionType === 'withdrawal'
                ? ($config['account_to_id'] ?? null)
                : ($config['account_from_id'] ?? null);

            $account = $accountsById->get($accountId);
            $payee = $accountsById->get($payeeId);

            if ($account) {
                $matchedEntities['account'] = [
                    'id' => $account->id,
                    'name' => $account->name,
                    'matched' => true,
                    'url' => route('account-entity.show', $account->id),
                ];
            }

            if ($payee) {
                $matchedEntities['payee'] = [
                    'id' => $payee->id,
                    'name' => $payee->name,
                    'matched' => true,
                    'url' => null,
                ];
            }
        } elseif (in_array($transactionType, ['buy', 'sell', 'dividend', 'interest', 'add_shares', 'remove_shares'], true)) {
            $account = $accountsById->get($config['account_id'] ?? null);
            $investment = $investmentsById->get($config['investment_id'] ?? null);

            if ($account) {
                $matchedEntities['account'] = [
                    'id' => $account->id,
                    'name' => $account->name,
                    'matched' => true,
                    'url' => route('account-entity.show', $account->id),
                ];
            }

            if ($investment) {
                $matchedEntities['investment'] = [
                    'id' => $investment->id,
                    'name' => $investment->name,
                    'matched' => true,
                    'url' => route('investment.show', ['investment' => $investment->id]),
                ];
            }
        }

        $processedData['matched_entities'] = $matchedEntities;
        $aiDocument->processed_transaction_data = $processedData;
    }
}
