<?php

namespace App\Http\Controllers;

use App\Models\TransactionImportRule;
use App\Models\AccountEntity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class TransactionImportRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rules = TransactionImportRule::where('user_id', Auth::id())
            ->with(['account', 'transferAccount', 'mergePayee'])
            ->orderBy('priority')
            ->get();

        return view('transaction-import-rules.index', compact('rules'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $accounts = Auth::user()->accounts()->orderBy('name')->get();
        $payees = Auth::user()->payees()->orderBy('name')->get();
        $prefillDescription = $request->input('description_pattern', null);

        return view('transaction-import-rules.form', compact('accounts', 'payees', 'prefillDescription'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:account_entities,id',
            'description_pattern' => 'required|string|max:255',
            'action' => 'required|in:convert_to_transfer,skip,modify,merge_payee',
            'transfer_account_id' => 'nullable|exists:account_entities,id',
            'merge_payee_id' => 'nullable|exists:account_entities,id',
            'transaction_type_id' => 'nullable|integer|min:1|max:11',
            'priority' => 'required|integer|min:1',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['use_regex'] = $request->has('use_regex');
        $validated['active'] = $request->has('active');
        $validated['append_original_to_comment'] = $request->has('append_original_to_comment');

        TransactionImportRule::create($validated);

        return redirect()->route('transaction-import-rules.index')
            ->with('success', 'Import rule created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TransactionImportRule $transactionImportRule)
    {
        // Ensure user owns this rule
        if ($transactionImportRule->user_id !== Auth::id()) {
            abort(403);
        }

        $accounts = Auth::user()->accounts()->orderBy('name')->get();
        $payees = Auth::user()->payees()->orderBy('name')->get();
        $rule = $transactionImportRule;

        return view('transaction-import-rules.form', compact('rule', 'accounts', 'payees'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TransactionImportRule $transactionImportRule)
    {
        // Ensure user owns this rule
        if ($transactionImportRule->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'account_id' => 'nullable|exists:account_entities,id',
            'description_pattern' => 'required|string|max:255',
            'action' => 'required|in:convert_to_transfer,skip,modify,merge_payee',
            'transfer_account_id' => 'nullable|exists:account_entities,id',
            'merge_payee_id' => 'nullable|exists:account_entities,id',
            'transaction_type_id' => 'nullable|integer|min:1|max:11',
            'priority' => 'required|integer|min:1',
        ]);

        $validated['use_regex'] = $request->has('use_regex');
        $validated['active'] = $request->has('active');
        $validated['append_original_to_comment'] = $request->has('append_original_to_comment');

        $transactionImportRule->update($validated);

        return redirect()->route('transaction-import-rules.index')
            ->with('success', 'Import rule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TransactionImportRule $transactionImportRule)
    {
        // Ensure user owns this rule
        if ($transactionImportRule->user_id !== Auth::id()) {
            abort(403);
        }

        $transactionImportRule->delete();

        return redirect()->route('transaction-import-rules.index')
            ->with('success', 'Import rule deleted successfully.');
    }

    /**
     * Test import rules against existing transactions.
     */
    public function test(Request $request)
    {
        $request->validate([
            'rule_id' => 'nullable|exists:transaction_import_rules,id',
            'days_back' => 'nullable|integer|min:1',
        ]);

        $daysBack = $request->input('days_back', 30);
        $ruleId = $request->input('rule_id');

        // Get active rules (or specific rule even if inactive for testing)
        $rulesQuery = TransactionImportRule::where('user_id', Auth::id())
            ->orderBy('priority');

        if ($ruleId) {
            $rulesQuery->where('id', $ruleId);
        } else {
            // Only active rules when testing all
            $rulesQuery->where('active', true);
        }

        $rules = $rulesQuery->get();

        // Get recent standard transactions only
        $transactions = Auth::user()
            ->transactions()
            ->with(['transactionItems', 'config', 'transactionType'])
            ->where('config_type', 'standard')
            ->where('schedule', false)
            ->where('budget', false)
            ->where('date', '>=', now()->subDays($daysBack))
            ->orderBy('date', 'desc')
            ->get();

        // Test each transaction against rules
        $matches = [];
        $debugInfo = [
            'total_transactions' => $transactions->count(),
            'total_rules' => $rules->count(),
            'transactions_checked' => 0,
        ];

        foreach ($transactions as $transaction) {
            $debugInfo['transactions_checked']++;

            // Get transaction description - prioritize payee name for import rule matching
            $description = null;
            $config = $transaction->config;

            if ($config) {
                $accountFrom = $config->accountFrom;
                $accountTo = $config->accountTo;

                // First try to get payee name (the non-account side) - this is what imports typically use
                if ($accountFrom && $accountFrom->config_type === 'payee') {
                    $description = $accountFrom->name;
                } elseif ($accountTo && $accountTo->config_type === 'payee') {
                    $description = $accountTo->name;
                }
            }

            // Fallback to transaction comment
            if (empty($description)) {
                $description = $transaction->comment;
            }

            // Fallback to first transaction item's comment
            if (empty($description) && $transaction->transactionItems->isNotEmpty()) {
                $description = $transaction->transactionItems->first()->comment;
            }

            if (empty($description)) {
                continue;
            }

            // Test against rules
            foreach ($rules as $rule) {
                // Check if rule applies to this account
                if ($rule->account_id) {
                    $config = $transaction->config;
                    $accountFrom = $config->account_from_id ?? null;
                    $accountTo = $config->account_to_id ?? null;

                    if ($accountFrom !== $rule->account_id && $accountTo !== $rule->account_id) {
                        continue;
                    }
                }

                if ($rule->matches($description)) {
                    $matches[] = [
                        'transaction' => $transaction,
                        'rule' => $rule,
                        'description' => $description,
                    ];
                    break; // First match only (like real import)
                }
            }
        }

        $allRules = TransactionImportRule::where('user_id', Auth::id())
            ->orderBy('priority')
            ->get();

        return view('transaction-import-rules.test', compact('matches', 'rules', 'allRules', 'daysBack', 'ruleId', 'debugInfo'));
    }

    /**
     * Apply corrections to matched transactions based on import rules.
     */
    public function applyCorrections(Request $request)
    {
        $request->validate([
            'corrections' => 'required|array',
            'corrections.*.transaction_id' => 'required|exists:transactions,id',
            'corrections.*.rule_id' => 'required|exists:transaction_import_rules,id',
        ]);

        $corrected = 0;
        $errors = [];

        foreach ($request->input('corrections', []) as $correction) {
            // Only process if checkbox was checked
            if (!isset($correction['apply'])) {
                continue;
            }

            try {
                $transaction = Auth::user()
                    ->transactions()
                    ->with(['config', 'transactionType'])
                    ->findOrFail($correction['transaction_id']);

                $rule = TransactionImportRule::where('user_id', Auth::id())
                    ->findOrFail($correction['rule_id']);

                // Apply the rule action
                $this->applyRuleToTransaction($transaction, $rule);
                $corrected++;
            } catch (Exception $e) {
                $errors[] = "Transaction {$correction['transaction_id']}: {$e->getMessage()}";
            }
        }

        if ($corrected > 0) {
            $message = "Successfully corrected {$corrected} transaction(s).";
            if (!empty($errors)) {
                $message .= ' Some errors occurred: ' . implode(', ', $errors);
            }
            return redirect()->route('transaction-import-rules.test')
                ->with('success', $message);
        }

        return redirect()->route('transaction-import-rules.test')
            ->with('error', 'No transactions were corrected. ' . implode(', ', $errors));
    }

    /**
     * Apply a rule's action to an existing transaction.
     */
    private function applyRuleToTransaction($transaction, $rule)
    {
        if (!$transaction->isStandard()) {
            throw new Exception('Can only apply rules to standard transactions');
        }

        switch ($rule->action) {
            case 'convert_to_transfer':
                if (!$rule->transfer_account_id) {
                    throw new Exception('Transfer account not specified');
                }

                // Validate transfer account exists and is an account entity
                $transferAccount = AccountEntity::find($rule->transfer_account_id);
                if (! $transferAccount) {
                    throw new Exception('Transfer account entity not found');
                }
                if ($transferAccount->config_type !== 'account') {
                    throw new Exception('Transfer account must be an account entity');
                }

                // Determine which side is the payee that needs replacing
                $config = $transaction->config;
                $accountFromIsPayee = $config->accountFrom && $config->accountFrom->config_type === 'payee';
                $accountToIsPayee = $config->accountTo && $config->accountTo->config_type === 'payee';

                if ($accountFromIsPayee) {
                    // Withdrawal: payee is FROM, real account is TO
                    // Replace the payee with the transfer account
                    $config->account_from_id = $rule->transfer_account_id;
                } elseif ($accountToIsPayee) {
                    // Deposit: real account is FROM, payee is TO
                    // Replace the payee with the transfer account
                    $config->account_to_id = $rule->transfer_account_id;
                } elseif ($config->account_from_id && !$config->account_to_id) {
                    // Only FROM set, set TO to transfer account
                    $config->account_to_id = $rule->transfer_account_id;
                } elseif ($config->account_to_id && !$config->account_from_id) {
                    // Only TO set, set FROM to transfer account
                    $config->account_from_id = $rule->transfer_account_id;
                }

                // Ensure transaction type is transfer
                $transferType = \App\Models\TransactionType::where('name', 'transfer')->first();
                if ($transferType) {
                    $transaction->transaction_type_id = $transferType->id;
                }

                // Save config and transaction together
                DB::transaction(function () use ($config, $transaction) {
                    $config->save();
                    $transaction->save();
                });
                break;

            case 'skip':
                // For existing transactions, we'll just mark them somehow or delete
                // For safety, let's just add a comment
                // Only mark if not already marked
                if (!str_contains($transaction->comment ?? '', '[MARKED FOR SKIP]')) {
                    $transaction->comment = '[MARKED FOR SKIP] ' . ($transaction->comment ?? '');
                    $transaction->save();
                }
                break;

            case 'modify':
                if ($rule->transaction_type_id) {
                    $transaction->transaction_type_id = $rule->transaction_type_id;
                    $transaction->save();
                }
                break;

            case 'merge_payee':
                if (!$rule->merge_payee_id) {
                    throw new Exception('Merge payee not specified');
                }

                // Validate merge payee exists and is a payee entity
                $mergePayee = AccountEntity::find($rule->merge_payee_id);
                if (!$mergePayee) {
                    throw new Exception('Merge payee entity not found');
                }
                if ($mergePayee->config_type !== 'payee') {
                    throw new Exception('Merge payee must be a payee entity');
                }

                // Get the current payee and check if it already matches the target
                $config = $transaction->config;
                $originalPayeeName = null;
                $currentPayeeId = null;

                if ($config->accountFrom && $config->accountFrom->config_type === 'payee') {
                    $currentPayeeId = $config->account_from_id;
                    $originalPayeeName = $config->accountFrom->name;
                } elseif ($config->accountTo && $config->accountTo->config_type === 'payee') {
                    $currentPayeeId = $config->account_to_id;
                    $originalPayeeName = $config->accountTo->name;
                }

                // Skip if payee already matches the target
                if ($currentPayeeId === $rule->merge_payee_id) {
                    break;
                }

                // Apply the merge
                if ($config->accountFrom && $config->accountFrom->config_type === 'payee') {
                    $config->account_from_id = $rule->merge_payee_id;
                } elseif ($config->accountTo && $config->accountTo->config_type === 'payee') {
                    $config->account_to_id = $rule->merge_payee_id;
                }

                // Append original payee name to comment if requested
                if ($rule->append_original_to_comment && $originalPayeeName) {
                    $currentComment = $transaction->comment ?? '';
                    $appendText = "Original: {$originalPayeeName}";

                    if (empty($currentComment)) {
                        $transaction->comment = $appendText;
                    } else {
                        $transaction->comment = $currentComment . ' | ' . $appendText;
                    }
                }

                // Save config and transaction together
                DB::transaction(function () use ($config, $transaction) {
                    $config->save();
                    $transaction->save();
                });
                break;
        }
    }
}
