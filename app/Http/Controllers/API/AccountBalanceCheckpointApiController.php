<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountBalanceCheckpointRequest;
use App\Models\AccountBalanceCheckpoint;
use App\Models\AccountEntity;
use App\Services\AdvancedReconcileService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;

class AccountBalanceCheckpointApiController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AdvancedReconcileService $advancedReconcileService,
    ) {
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
        ];
    }

    /**
     * @throws AuthorizationException
     */
    public function accountSummary(Request $request, AccountEntity $accountEntity): JsonResponse
    {
        Gate::authorize('view', $accountEntity);

        if (!$accountEntity->isAccount()) {
            return response()->json([
                'message' => __('This account entity is not an account.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateTo = Carbon::parse($validated['date_to'] ?? now()->toDateString())->startOfDay();
        $dateFrom = Carbon::parse($validated['date_from'] ?? $dateTo->copy()->startOfMonth()->toDateString())->startOfDay();

        return response()->json(
            $this->advancedReconcileService->accountSummary($accountEntity, $dateFrom, $dateTo),
            Response::HTTP_OK
        );
    }

    /**
     * @throws AuthorizationException
     */
    public function store(AccountBalanceCheckpointRequest $request, AccountEntity $accountEntity): JsonResponse
    {
        Gate::authorize('update', $accountEntity);

        if (!$accountEntity->isAccount()) {
            return response()->json([
                'message' => __('This account entity is not an account.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validated();

        $checkpoint = AccountBalanceCheckpoint::create([
            'user_id' => $request->user()->id,
            'account_entity_id' => $accountEntity->id,
            'checkpoint_date' => $validated['checkpoint_date'],
            'checkpoint_type' => $validated['checkpoint_type'],
            'balance' => $validated['balance'],
            'note' => $validated['note'] ?? null,
            'active' => true,
            'source' => $validated['source'] ?? 'manual',
            'source_document_id' => $validated['source_document_id'] ?? null,
        ]);

        return response()->json([
            'checkpoint' => $checkpoint,
            'message' => __('Checkpoint saved'),
        ], Response::HTTP_CREATED);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'checkpoint_type' => ['nullable', 'in:cash,investment,total'],
            'display' => ['nullable', 'in:status,balance'],
        ]);

        $checkpointType = $validated['checkpoint_type'] ?? 'total';

        $months = collect(range(0, 11))
            ->map(fn (int $offset): Carbon => now()->startOfMonth()->subMonths($offset));

        $accounts = $request->user()
            ->accounts()
            ->active()
            ->with(['config', 'config.currency'])
            ->orderBy('name')
            ->get();

        $rows = $accounts->map(fn (AccountEntity $accountEntity): array => [
            'account' => [
                'id' => $accountEntity->id,
                'name' => $accountEntity->name,
                'currency' => $accountEntity->config->currency ?? null,
            ],
            'months' => $months->mapWithKeys(fn (Carbon $month): array => [
                $month->format('Y-m') => $this->advancedReconcileService->dashboard($accountEntity, $month, $checkpointType),
            ]),
        ]);

        return response()->json([
            'checkpoint_type' => $checkpointType,
            'display' => $validated['display'] ?? 'status',
            'months' => $months->map(fn (Carbon $month): array => [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
            ])->values(),
            'rows' => $rows,
        ], Response::HTTP_OK);
    }
}
