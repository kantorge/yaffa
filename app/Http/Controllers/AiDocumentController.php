<?php

namespace App\Http\Controllers;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
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
            ['auth', 'verified'],
            new Middleware('can:viewAny,App\Models\AiDocument', only: ['index']),
            new Middleware('can:view,aiDocument', only: ['show', 'file']),
        ];
    }

    /**
     * Display a listing of AI documents.
     */
    public function index(Request $request): View
    {
        /**
         * @get('/ai-documents')
         * @name('ai-documents.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\AiDocument')
         */
        $documents = $request->user()
            ->aiDocuments()
            ->with(['files', 'receivedMail', 'transaction'])
            ->latest()
            ->get();

        JavaScriptFacade::put([
            'aiDocuments' => $documents,
            'aiDocumentStatusLabels' => AiDocument::statusLabels(),
            'aiDocumentSourceLabels' => AiDocument::sourceLabels(),
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
         * @get('/ai-documents/{aiDocument}')
         * @name('ai-documents.show')
         * @middlewares('web', 'auth', 'verified', 'can:view,aiDocument')
         */
        $aiDocument->load(['files', 'receivedMail', 'transaction']);

        $duplicateWarnings = [];
        if ($aiDocument->processed_transaction_data
            && array_key_exists('duplicate_warnings', $aiDocument->processed_transaction_data)) {
            $duplicateWarnings = $aiDocument->processed_transaction_data['duplicate_warnings'];
        }

        JavaScriptFacade::put([
            'aiDocument' => $aiDocument,
            'aiDocumentStatusLabels' => AiDocument::statusLabels(),
            'aiDocumentSourceLabels' => AiDocument::sourceLabels(),
            'aiDocumentDuplicateWarnings' => $duplicateWarnings,
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
         * @get('/ai-documents/{aiDocument}/files/{aiDocumentFile}')
         * @name('ai-documents.files.show')
         * @middlewares('web', 'auth', 'verified', 'can:view,aiDocument')
         */
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
