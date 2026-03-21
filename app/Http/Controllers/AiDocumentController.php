<?php

namespace App\Http\Controllers;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
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
        private AiUserSettingsResolver $aiUserSettingsResolver
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
                'aiSettingsUrl' => route('user.ai-settings'),
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

        // Enrich processed transaction data with category full names and matched entities
        $this->enrichProcessedData($aiDocument);

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
    private function enrichProcessedData(AiDocument $aiDocument): void
    {
        $processedData = $aiDocument->processed_transaction_data;

        if (! $processedData) {
            return;
        }

        if (isset($processedData['transaction_items']) && is_array($processedData['transaction_items'])) {
            // Collect all unique recommended category IDs from items
            $categoryIds = collect($processedData['transaction_items'])
                ->map(fn ($item) => $item['recommended_category_id'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (! empty($categoryIds)) {
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
                    'url' => route('investments.show', ['investment' => $investment->id]),
                ];
            }
        }

        $processedData['matched_entities'] = $matchedEntities;

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
