<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountBalanceCheckpoint;
use App\Services\BalanceCheckpointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BalanceCheckpointApiController extends Controller
{
    protected BalanceCheckpointService $service;

    public function __construct(BalanceCheckpointService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of checkpoints for an account.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_entity_id' => 'required|exists:account_entities,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $checkpoints = AccountBalanceCheckpoint::active()
            ->forAccount($request->account_entity_id)
            ->where('user_id', Auth::id())
            ->orderBy('checkpoint_date', 'desc')
            ->with('user', 'accountEntity')
            ->get();

        // Add real-time match status to each checkpoint
        $balanceService = new BalanceCheckpointService();
        $checkpoints->each(function ($checkpoint) use ($balanceService) {
            $currentBalance = $balanceService->calculateBalanceAtDate(
                $checkpoint->account_entity_id,
                $checkpoint->checkpoint_date
            );
            $checkpoint->current_balance = $currentBalance;
            $checkpoint->matches = abs($currentBalance - $checkpoint->balance) < 0.01;
            $checkpoint->status = $checkpoint->matches ? 'matched' : 'not matched';
        });

        return response()->json($checkpoints);
    }

    /**
     * Store a newly created checkpoint.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_entity_id' => 'required|exists:account_entities,id',
            'checkpoint_date' => 'required|date',
            'balance' => 'required|numeric',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $checkpoint = $this->service->createCheckpoint(
            Auth::id(),
            $request->account_entity_id,
            Carbon::parse($request->checkpoint_date),
            $request->balance,
            $request->note
        );

        return response()->json($checkpoint->load('user', 'accountEntity'), 201);
    }

    /**
     * Display the specified checkpoint.
     */
    public function show(AccountBalanceCheckpoint $checkpoint)
    {
        // Verify ownership
        if ($checkpoint->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($checkpoint->load('user', 'accountEntity'));
    }

    /**
     * Update the specified checkpoint.
     */
    public function update(Request $request, AccountBalanceCheckpoint $checkpoint)
    {
        // Verify ownership
        if ($checkpoint->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'balance' => 'required|numeric',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $checkpoint->update([
            'balance' => $request->balance,
            'note' => $request->note,
        ]);

        return response()->json($checkpoint->load('user', 'accountEntity'));
    }

    /**
     * Remove the specified checkpoint (deactivate).
     */
    public function destroy(AccountBalanceCheckpoint $checkpoint)
    {
        // Verify ownership
        if ($checkpoint->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $checkpoint->update(['active' => false]);

        return response()->json(['message' => 'Checkpoint deactivated successfully']);
    }

    /**
     * Toggle reconciliation status of a transaction.
     */
    public function toggleReconciliation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = \App\Models\Transaction::findOrFail($request->transaction_id);

        // Verify ownership
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($transaction->reconciled) {
            $this->service->unreconcileTransaction($transaction);
            $message = 'Transaction unreconciled successfully';
        } else {
            $this->service->reconcileTransaction($transaction, Auth::id());
            $message = 'Transaction reconciled successfully';
        }

        return response()->json([
            'message' => $message,
            'reconciled' => $transaction->fresh()->reconciled,
        ]);
    }

    /**
     * Check balance integrity at a checkpoint.
     */
    public function checkIntegrity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_entity_id' => 'required|exists:account_entities,id',
            'checkpoint_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $date = Carbon::parse($request->checkpoint_date);
        $calculatedBalance = $this->service->calculateBalanceAtDate($request->account_entity_id, $date);

        $checkpoint = AccountBalanceCheckpoint::active()
            ->forAccount($request->account_entity_id)
            ->where('checkpoint_date', $date)
            ->first();

        return response()->json([
            'calculated_balance' => $calculatedBalance,
            'checkpoint_balance' => $checkpoint?->balance,
            'matches' => $checkpoint ? abs($calculatedBalance - $checkpoint->balance) < 0.01 : null,
        ]);
    }
}
