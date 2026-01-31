<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiDocumentRequest;
use App\Http\Requests\UpdateAiDocumentRequest;
use App\Jobs\AiProcessingJob;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
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
            ['auth:sanctum', 'verified'],
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
            'status' => 'draft',
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

        // Move document to ready_for_processing
        $document->status = 'ready_for_processing';
        $document->save();

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
    public function update(UpdateAiDocumentRequest $request, AiDocument $document): JsonResponse
    {
        Gate::authorize('update', $document);

        if ($request->filled('custom_prompt')) {
            $document->custom_prompt = $request->input('custom_prompt');
        }

        if ($request->filled('status')) {
            $document->status = $request->input('status');
        }

        $document->save();

        return response()->json([
            'id' => $document->id,
            'status' => $document->status,
            'custom_prompt' => $document->custom_prompt,
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
            ->with('aiDocumentFiles');

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
    public function show(AiDocument $document): JsonResponse
    {
        Gate::authorize('view', $document);

        $document->load('aiDocumentFiles', 'receivedMail');

        // Build duplicate warnings if available
        $duplicateWarnings = [];
        if ($document->processed_transaction_data && isset($document->processed_transaction_data['duplicate_warnings'])) {
            $duplicateWarnings = $document->processed_transaction_data['duplicate_warnings'];
        }

        return response()->json([
            'document' => $document,
            'duplicate_warnings' => $duplicateWarnings,
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/documents/{id}/reprocess - Trigger document reprocessing
     *
     * @throws AuthorizationException
     */
    public function reprocess(AiDocument $document): JsonResponse
    {
        Gate::authorize('reprocess', $document);

        // Only allow reprocessing if document is in a terminal or failed state
        if (! in_array($document->status, ['ready_for_review', 'processing_failed', 'finalized'])) {
            return response()->json([
                'error' => __('Document cannot be reprocessed from current status'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Reset document to ready_for_processing
        $document->status = 'ready_for_processing';
        $document->processed_transaction_data = null;
        $document->processed_at = null;
        $document->save();

        // Dispatch processing job
        AiProcessingJob::dispatch($document);

        return response()->json([
            'status' => $document->status,
            'message' => __('Document reprocessing queued'),
        ], Response::HTTP_OK);
    }

    /**
     * DELETE /api/documents/{id} - Delete a document and its files
     *
     * @throws AuthorizationException
     */
    public function destroy(AiDocument $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        // Delete stored files
        foreach ($document->aiDocumentFiles as $file) {
            Storage::disk('local')->delete($file->file_path);
        }

        $document->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Store an uploaded file for the document
     */
    private function storeFile(AiDocument $document, $file): void
    {
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileType = $this->getFileType($extension);

        // Store file
        $path = $file->storeAs(
            "ai_documents/{$document->user_id}/{$document->id}",
            $filename,
            'local'
        );

        // Create database record
        AiDocumentFile::create([
            'ai_document_id' => $document->id,
            'file_path' => $path,
            'file_name' => $filename,
            'file_type' => $fileType,
        ]);
    }

    /**
     * Store text input as a file
     */
    private function storeTextFile(AiDocument $document, string $textInput): void
    {
        $filename = 'text_input_' . now()->timestamp . '.txt';

        $path = Storage::disk('local')->put(
            "ai_documents/{$document->user_id}/{$document->id}/{$filename}",
            $textInput
        );

        AiDocumentFile::create([
            'ai_document_id' => $document->id,
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

        return match ($extension) {
            'pdf' => 'pdf',
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'txt' => 'txt',
            default => 'txt',
        };
    }
}
