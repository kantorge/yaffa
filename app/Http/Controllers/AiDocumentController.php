<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use App\Services\TransactionDraftService;
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
    public function __construct(
        private AiUserSettingsResolver $aiUserSettingsResolver,
        private TransactionDraftService $transactionDraftService,
    ) {
    }

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
        /** @var User $user */
        $user = $request->user();

        JavaScriptFacade::put([
            'aiDocumentStatusLabels' => AiDocument::statusLabels(),
            'aiDocumentSourceLabels' => AiDocument::sourceLabels(),
            'aiDocumentConfig' => [
                'maxFilesPerSubmission' => config('ai-documents.file_upload.max_files_per_submission'),
                'maxFileSize' => config('ai-documents.file_upload.max_file_size_mb'),
                'allowedTypes' => config('ai-documents.file_upload.allowed_types'),
                'aiProcessingEnabled' => $this->aiUserSettingsResolver->isEnabledForUser($user),
            ],
        ]);

        return view('ai-documents.index');
    }

    /**
     * Display a single AI document.
     *
     * @throws AuthorizationException
     */
    public function show(Request $request, AiDocument $aiDocument): View
    {
        /**
         * @get("/ai-documents/{aiDocument}")
         * @name("ai-documents.show")
         * @middlewares("web", "auth", "verified")
         */
        $aiDocument->load(['files', 'receivedMail', 'transaction']);

        // Enrich processed transaction data with category full names and matched entities
        if ($aiDocument->processed_transaction_data) {
            $aiDocument->processed_transaction_data = $this->transactionDraftService->enrich($aiDocument->processed_transaction_data, $request->user());
        }

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
     * Stream or download a file belonging to an AI document.
     */
    public function file(Request $request, AiDocument $aiDocument, AiDocumentFile $aiDocumentFile): SymfonyResponse
    {
        /**
         * @get("/ai-documents/{aiDocument}/files/{aiDocumentFile}")
         * @name("ai-documents.files.show")
         * @middlewares("web", "auth", "verified")
         */
        Gate::authorize('view', $aiDocument);
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
