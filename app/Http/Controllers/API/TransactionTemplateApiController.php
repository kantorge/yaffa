<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionTemplateRequest;
use App\Models\TransactionTemplate;
use App\Services\TransactionDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: ['index', 'show'])]
#[Middleware('abilities:write', only: ['store', 'update', 'destroy'])]
class TransactionTemplateApiController extends Controller
{
    public function __construct(private TransactionDraftService $draftService)
    {
    }

    /**
     * List transaction templates
     *
     * Most used first. Use `featured=1` to only get the templates shown on the dashboard, and `limit` to cap
     * the number of rows.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'featured' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $templates = $request->user()
            ->transactionTemplates()
            ->with('payee:id,name')
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->orderByDesc('use_count')
            ->orderByDesc('last_used_at')
            ->orderBy('name')
            ->when(isset($validated['limit']), fn ($query) => $query->limit($validated['limit']))
            ->get();

        return response()->json($templates->map(fn (TransactionTemplate $template) => $this->summary($template)));
    }

    /**
     * Get a transaction template
     *
     * Returns the draft in the same shape as an AI document draft, with references that no longer resolve
     * (deleted, inactive or foreign) removed and listed in `notices`.
     *
     * @throws AuthorizationException
     */
    #[Authorize('view', 'template')]
    public function show(Request $request, TransactionTemplate $template): JsonResponse
    {
        ['draft' => $draft, 'notices' => $notices] = $this->draftService->normalize($template->draft, $request->user());

        return response()->json([
            'template' => $this->summary($template->loadMissing('payee:id,name')),
            'draft' => $this->draftService->enrich($draft, $request->user()),
            'notices' => $notices,
        ]);
    }

    /**
     * Create a transaction template
     */
    public function store(TransactionTemplateRequest $request): JsonResponse
    {
        // The draft is not part of validated(): it has nested rules for the forbidden keys only, and its
        // structure is validated by normalize()
        ['draft' => $draft, 'notices' => $notices] = $this->draftService->normalize($request->array('draft'), $request->user());

        $template = new TransactionTemplate([...$request->validated(), 'draft' => $draft]);
        $template->user_id = $request->user()->id;
        $template->payee_id = $this->draftService->payeeIdFromDraft($draft, $request->user());
        $template->save();

        return response()->json([
            'template' => $this->summary($template->load('payee:id,name')),
            'notices' => $notices,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a transaction template
     *
     * Any of `name`, `is_featured` and `draft` can be sent. A new draft replaces the old one.
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'template')]
    public function update(TransactionTemplateRequest $request, TransactionTemplate $template): JsonResponse
    {
        $validated = $request->validated();
        $notices = [];

        if ($request->has('draft')) {
            ['draft' => $validated['draft'], 'notices' => $notices] = $this->draftService->normalize($request->array('draft'), $request->user());
            $template->payee_id = $this->draftService->payeeIdFromDraft($validated['draft'], $request->user());
        }

        $template->update($validated);

        return response()->json([
            'template' => $this->summary($template->load('payee:id,name')),
            'notices' => $notices,
        ]);
    }

    /**
     * Delete a transaction template
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'template')]
    public function destroy(TransactionTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['template' => $template]);
    }

    /**
     * The list view of a template: no draft, but what a table or button needs.
     */
    private function summary(TransactionTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'config_type' => $template->draft['config_type'] ?? null,
            'transaction_type' => $template->draft['transaction_type'] ?? null,
            'item_count' => count($template->draft['transaction_items'] ?? []),
            'payee_id' => $template->payee_id,
            'payee_name' => $template->payee?->name,
            'is_featured' => $template->is_featured,
            'use_count' => $template->use_count,
            'last_used_at' => $template->last_used_at,
        ];
    }
}
