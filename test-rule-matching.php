<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get transaction 2815
$transaction = \App\Models\Transaction::with(['transactionItems', 'config.accountFrom', 'config.accountTo', 'transactionType'])
    ->find(2815);

if (!$transaction) {
    echo "Transaction 2815 not found\n";
    exit(1);
}

echo "=== TRANSACTION 2815 ===\n";
echo "Config Type: {$transaction->config_type}\n";
echo "Schedule: " . ($transaction->schedule ? 'true' : 'false') . "\n";
echo "Budget: " . ($transaction->budget ? 'true' : 'false') . "\n";
echo "Date: {$transaction->date->format('Y-m-d')}\n";
echo "Comment: " . ($transaction->comment ?? 'NULL') . "\n";

if ($transaction->transactionItems->first()) {
    echo "First Item Comment: " . ($transaction->transactionItems->first()->comment ?? 'NULL') . "\n";
}

$config = $transaction->config;
if ($config->accountFrom) {
    echo "Account From: {$config->accountFrom->name} (ID: {$config->accountFrom->id}, Type: {$config->accountFrom->config_type})\n";
}
if ($config->accountTo) {
    echo "Account To: {$config->accountTo->name} (ID: {$config->accountTo->id}, Type: {$config->accountTo->config_type})\n";
}

// Build description like the test method does (prioritize payee name)
$description = null;

// First try payee name
if ($config->accountFrom && $config->accountFrom->config_type === 'payee') {
    $description = $config->accountFrom->name;
} elseif ($config->accountTo && $config->accountTo->config_type === 'payee') {
    $description = $config->accountTo->name;
}

// Fallback to transaction comment
if (empty($description)) {
    $description = $transaction->comment;
}

// Fallback to first transaction item's comment
if (empty($description) && $transaction->transactionItems->isNotEmpty()) {
    $description = $transaction->transactionItems->first()->comment;
}

echo "\nExtracted Description: " . ($description ?? 'EMPTY') . "\n";

// Get rule 3
$rule = \App\Models\TransactionImportRule::with(['account', 'transferAccount'])->find(3);

if (!$rule) {
    echo "\nRule 3 not found\n";
    exit(1);
}

echo "\n=== RULE #3 ===\n";
echo "Pattern: {$rule->description_pattern}\n";
echo "Use Regex: " . ($rule->use_regex ? 'true' : 'false') . "\n";
echo "Account ID: " . ($rule->account_id ?? 'NULL (all accounts)') . "\n";
echo "Active: " . ($rule->active ? 'true' : 'false') . "\n";
echo "Action: {$rule->action}\n";

if ($rule->transferAccount) {
    echo "Transfer Account: {$rule->transferAccount->name}\n";
}

// Test if account matches
$accountMatches = true;
if ($rule->account_id) {
    $accountFrom = $config->account_from_id ?? null;
    $accountTo = $config->account_to_id ?? null;
    $accountMatches = ($accountFrom == $rule->account_id || $accountTo == $rule->account_id);
}

echo "\n=== MATCHING TEST ===\n";
echo "Account filter matches: " . ($accountMatches ? 'YES' : 'NO') . "\n";

if (empty($description)) {
    echo "Description is EMPTY - would skip\n";
} else {
    $matches = $rule->matches($description);
    echo "Pattern matches description: " . ($matches ? 'YES' : 'NO') . "\n";
    
    if ($matches && $accountMatches) {
        echo "\n✓ THIS TRANSACTION SHOULD BE FOUND!\n";
    } else {
        echo "\n✗ This transaction would NOT be found\n";
        if (!$matches) echo "  Reason: Pattern doesn't match\n";
        if (!$accountMatches) echo "  Reason: Account filter doesn't match\n";
    }
}

// Test the query that the controller uses
echo "\n=== QUERY TEST ===\n";
$userId = $transaction->user_id;
$testTransactions = \App\Models\Transaction::where('user_id', $userId)
    ->where('config_type', 'standard')
    ->where('schedule', false)
    ->where('budget', false)
    ->where('date', '>=', now()->subDays(365))
    ->where('id', 2815)
    ->count();

echo "Transaction 2815 found in query: " . ($testTransactions > 0 ? 'YES' : 'NO') . "\n";

if ($testTransactions == 0) {
    echo "\nChecking why transaction not found in query:\n";
    
    $check = \App\Models\Transaction::where('id', 2815)->first();
    echo "- user_id match: " . ($check->user_id == $userId ? 'YES' : 'NO') . "\n";
    echo "- config_type == 'standard': " . ($check->config_type == 'standard' ? 'YES' : 'NO') . "\n";
    echo "- schedule == false: " . (!$check->schedule ? 'YES' : 'NO') . "\n";
    echo "- budget == false: " . (!$check->budget ? 'YES' : 'NO') . "\n";
    echo "- date >= " . now()->subDays(365)->format('Y-m-d') . ": " . ($check->date >= now()->subDays(365) ? 'YES' : 'NO') . "\n";
}
