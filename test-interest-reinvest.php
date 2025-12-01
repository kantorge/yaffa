<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

echo "Testing Interest ReInvest Creation\n";
echo "===================================\n\n";

// Find an account and investment for testing
$account = Account::first();
if (!$account) {
    echo "ERROR: No account found in database\n";
    exit(1);
}

$investment = \App\Models\Investment::first();
if (!$investment) {
    echo "ERROR: No investment found in database\n";
    exit(1);
}

echo "Using Account: {$account->id} - {$account->name}\n";
echo "Using Investment: {$investment->id} - {$investment->name}\n\n";

// Get transaction count before
$countBefore = Transaction::count();
echo "Transaction count before: {$countBefore}\n\n";

try {
    DB::beginTransaction();
    
    echo "Creating Interest ReInvest config...\n";
    $config = TransactionDetailInvestment::create([
        'account_id' => $account->id,
        'investment_id' => $investment->id,
        'price' => 1,
        'quantity' => 100,
        'dividend' => 100,
        'commission' => 0,
        'tax' => 0,
    ]);
    echo "Config created with ID: {$config->id}\n\n";
    
    echo "Creating Interest ReInvest transaction...\n";
    $transaction = new Transaction([
        'user_id' => 1,
        'date' => now(),
        'transaction_type_id' => 13, // Interest ReInvest
        'config_type' => 'investment',
        'config_id' => $config->id,
        'schedule' => false,
        'budget' => false,
        'reconciled' => false,
        'comment' => 'Test Interest ReInvest',
    ]);
    
    echo "About to save transaction (this should trigger observer)...\n";
    $saved = $transaction->save();
    
    echo "Save returned: " . ($saved ? 'true' : 'false') . "\n";
    echo "Transaction ID: " . ($transaction->id ?? 'NULL') . "\n\n";
    
    $countAfter = Transaction::count();
    echo "Transaction count after: {$countAfter}\n";
    echo "Expected: {$countBefore} + 2 = " . ($countBefore + 2) . "\n";
    echo "Actual change: " . ($countAfter - $countBefore) . "\n\n";
    
    // Check for Interest and Buy transactions
    $interestTransactions = Transaction::where('transaction_type_id', 5)
        ->where('comment', 'LIKE', '%Test Interest ReInvest%')
        ->get();
    echo "Interest yield transactions created: {$interestTransactions->count()}\n";
    
    $buyTransactions = Transaction::where('transaction_type_id', 4)
        ->where('comment', 'LIKE', '%Test Interest ReInvest%')
        ->get();
    echo "Buy transactions created: {$buyTransactions->count()}\n\n";
    
    if ($interestTransactions->count() > 0) {
        $t = $interestTransactions->first();
        echo "Interest transaction details:\n";
        echo "  ID: {$t->id}\n";
        echo "  Cashflow: " . ($t->cashflow_value ?? 'NULL') . "\n";
        echo "  Currency ID: " . ($t->currency_id ?? 'NULL') . "\n\n";
    }
    
    if ($buyTransactions->count() > 0) {
        $t = $buyTransactions->first();
        echo "Buy transaction details:\n";
        echo "  ID: {$t->id}\n";
        echo "  Cashflow: " . ($t->cashflow_value ?? 'NULL') . "\n";
        echo "  Currency ID: " . ($t->currency_id ?? 'NULL') . "\n\n";
    }
    
    DB::rollBack();
    echo "Transaction rolled back (test mode)\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nTest completed successfully!\n";
