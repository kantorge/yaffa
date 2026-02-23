<?php

namespace App\Http\Controllers;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\Category;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AiDocumentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:viewAny,' . AiDocument::class, only: ['index']),
            new Middleware('can:view,aiDocument', only: ['show', 'file']),
        ];
    }

    /**
     * Display a listing of AI documents.
     */
    public function index(Request $request): View
    {
        /**
         * @get("/ai-documents")
         * @name("ai-documents.index")
         * @middlewares("web", "auth", "verified")
         */
        JavaScriptFacade::put([
            'aiDocumentStatusLabels' => AiDocument::statusLabels(),
            'aiDocumentSourceLabels' => AiDocument::sourceLabels(),
            'aiDocumentConfig' => [
                'maxFilesPerSubmission' => config('ai-documents.file_upload.max_files_per_submission'),
                'maxFileSize' => config('ai-documents.file_upload.max_file_size_mb'),
                'allowedTypes' => config('ai-documents.file_upload.allowed_types'),
            ],
        ]);

        return view('ai-documents.index');
    }

    /**
     * Display a single AI document.
     *
     * @throws AuthorizationException
     */
    public function show(AiDocument $aiDocument): View
    {
        /**
         * @get("/ai-documents/{aiDocument}")
         * @name("ai-documents.show")
         * @middlewares("web", "auth", "verified")
         */
        $aiDocument->load(['files', 'receivedMail', 'transaction']);

        // Enrich processed transaction data with category full names
        $this->enrichProcessedDataWithCategories($aiDocument);

        JavaScriptFacade::put([
            'aiDocument' => $aiDocument,
            'aiDocumentStatusLabels' => AiDocument::statusLabels(),
            'aiDocumentSourceLabels' => AiDocument::sourceLabels()
        ]);

        return view('ai-documents.show', [
            'aiDocument' => $aiDocument,
        ]);
    }

    /**
     * Enrich processed transaction data with category full names
     */
    private function enrichProcessedDataWithCategories(AiDocument $aiDocument): void
    {
        $processedData = $aiDocument->processed_transaction_data;

        if (!$processedData || !isset($processedData['transaction_items']) || !is_array($processedData['transaction_items'])) {
            return;
        }

        // Collect all unique recommended category IDs from items
        $categoryIds = collect($processedData['transaction_items'])
            ->map(fn ($item) => $item['recommended_category_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($categoryIds)) {
            return;
        }

        // Load all categories in one query
        $categories = Category::query()
            ->with('parent')
            ->whereIn('id', $categoryIds)
            ->where('user_id', $aiDocument->user_id)
            ->get()
            ->keyBy('id');

        // Enrich each item with recommended category objects and full names
        foreach ($processedData['transaction_items'] as &$item) {
            if (isset($item['recommended_category_id']) && $categories->has($item['recommended_category_id'])) {
                $recommendedCategory = $categories->get($item['recommended_category_id']);
                $item['recommended_category_full_name'] = $recommendedCategory->full_name;
            }
        }

        // Update the model's attribute (this won't save to DB, just for this request)
        $aiDocument->processed_transaction_data = $processedData;
    }

    /**
     * Stream or download a file belonging to an AI document.
     */
    public function file(Request $request, AiDocument $aiDocument, AiDocumentFile $aiDocumentFile): SymfonyResponse
    {
        /**
         * @get("/ai-documents/{aiDocument}/files/{aiDocumentFile}")
         * @name("ai-documents.files.show")
         * @middlewares("web", "auth", "verified")
         */
        $this->authorize('view', $aiDocument);
        if ($aiDocumentFile->ai_document_id !== $aiDocument->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (!Storage::disk('local')->exists($aiDocumentFile->file_path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($aiDocumentFile->file_path, $aiDocumentFile->file_name);
        }

        $path = Storage::disk('local')->path($aiDocumentFile->file_path);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . $aiDocumentFile->file_name . '"',
        ]);
    }

}
