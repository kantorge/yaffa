<?php

namespace App\Http\Controllers;

use App\Models\TransactionImportRule;
use App\Models\AccountEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionImportRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rules = TransactionImportRule::where('user_id', Auth::id())
            ->with(['account', 'transferAccount'])
            ->orderBy('priority')
            ->get();

        return view('transaction-import-rules.index', compact('rules'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $accounts = Auth::user()->accounts()->orderBy('name')->get();
        
        return view('transaction-import-rules.form', compact('accounts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:account_entities,id',
            'description_pattern' => 'required|string|max:255',
            'action' => 'required|in:convert_to_transfer,skip,modify',
            'transfer_account_id' => 'nullable|exists:account_entities,id',
            'transaction_type_id' => 'nullable|integer|min:1|max:11',
            'priority' => 'required|integer|min:1',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['use_regex'] = $request->has('use_regex');
        $validated['active'] = $request->has('active');

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
        $rule = $transactionImportRule;
        
        return view('transaction-import-rules.form', compact('rule', 'accounts'));
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
            'action' => 'required|in:convert_to_transfer,skip,modify',
            'transfer_account_id' => 'nullable|exists:account_entities,id',
            'transaction_type_id' => 'nullable|integer|min:1|max:11',
            'priority' => 'required|integer|min:1',
        ]);

        $validated['use_regex'] = $request->has('use_regex');
        $validated['active'] = $request->has('active');

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
}
