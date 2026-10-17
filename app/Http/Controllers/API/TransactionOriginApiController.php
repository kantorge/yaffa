<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiDocument;
use App\Models\TransactionOrigin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Validation\Rule;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: ['index'])]
#[Middleware('abilities:write', only: ['review', 'reviewMany'])]
class TransactionOriginApiController extends Controller
{
    /**
     * List transaction origins
     *
     * Where a transaction came from, or which document matched it. Use `relation=created&reviewed=0` for the
     * automatically recorded transactions nobody has confirmed yet, and `relation=conflicts_with` for the
     * receipts that contradict one. Newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relation' => ['sometimes', Rule::in([
                TransactionOrigin::RELATION_CREATED,
                TransactionOrigin::RELATION_DUPLICATE_OF,
                TransactionOrigin::RELATION_CONFLICTS_WITH,
            ])],
            'reviewed' => ['sometimes', 'boolean'],
        ]);

        $origins = TransactionOrigin::query()
            ->where('user_id', $request->user()->id)
            ->with('transaction')
            ->when(isset($validated['relation']), fn ($query) => $query->where('relation', $validated['relation']))
            ->when($request->has('reviewed'), fn ($query) => $request->boolean('reviewed')
                ? $query->whereNotNull('reviewed_at')
                : $query->whereNull('reviewed_at'))
            ->latest('id')
            ->limit(200)
            ->get();

        $documents = AiDocument::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $origins->where('origin_type', 'ai_document')->pluck('origin_id')->filter())
            ->get()
            ->keyBy('id');

        return response()->json($origins->map(function (TransactionOrigin $origin) use ($documents): array {
            $origin->transaction->loadDetails();
            $document = $origin->origin_type === 'ai_document' ? $documents->get($origin->origin_id) : null;

            return [
                'id' => $origin->id,
                'relation' => $origin->relation,
                'decision_reason' => $origin->decision_reason,
                'reviewed_at' => $origin->reviewed_at,
                'created_at' => $origin->created_at,
                'transaction' => $origin->transaction,
                'ai_document' => $document === null ? null : [
                    'id' => $document->id,
                    'status' => $document->status,
                    'document_kind' => $document->document_kind,
                    'date' => $document->processed_transaction_data['date'] ?? null,
                    'amount' => $document->processed_transaction_data['config']['amount_from'] ?? null,
                    'items' => $document->processed_transaction_data['transaction_items'] ?? [],
                ],
            ];
        }));
    }

    /**
     * Mark a transaction origin as reviewed
     *
     * @throws AuthorizationException
     */
    #[Authorize('update', 'origin')]
    public function review(TransactionOrigin $origin): JsonResponse
    {
        $origin->reviewed_at ??= now();
        $origin->save();

        return response()->json(['origin' => $origin]);
    }

    /**
     * Mark several transaction origins as reviewed
     *
     * Origins that are not the user's own are ignored.
     */
    public function reviewMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $count = TransactionOrigin::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->whereNull('reviewed_at')
            ->update(['reviewed_at' => now()]);

        return response()->json(['reviewed' => $count]);
    }
}
