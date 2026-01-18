<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check import 234
$import = App\Models\ImportJob::find(234);

echo "=== Import Job 234 ===" . PHP_EOL;
echo "Status: " . $import->status . PHP_EOL;
echo "Processed Rows: " . $import->processed_rows . PHP_EOL;
echo "File: " . $import->file_path . PHP_EOL;
echo PHP_EOL;

// Get transactions
$transactions = App\Models\Transaction::where('import_job_id', 234)
    ->with(['config', 'transactionItems.category'])
    ->take(10)
    ->get();

echo "=== Sample Transactions (first 10) ===" . PHP_EOL;
foreach ($transactions as $t) {
    echo PHP_EOL;
    echo "Transaction ID: " . $t->id . PHP_EOL;
    echo "Date: " . $t->date . PHP_EOL;
    echo "Type: " . $t->transaction_type_id . " (" . ($t->config_type ?? 'N/A') . ")" . PHP_EOL;

    if ($t->config) {
        echo "Config Type: " . get_class($t->config) . PHP_EOL;
        if ($t->config instanceof App\Models\TransactionDetailStandard) {
            echo "Amount: " . ($t->config->amount ?? 'N/A') . PHP_EOL;
            echo "Account From: " . ($t->config->account_from_id ?? 'N/A') . PHP_EOL;
            echo "Account To: " . ($t->config->account_to_id ?? 'N/A') . PHP_EOL;
        }
    }

    echo "Transaction Items: " . $t->transactionItems->count() . PHP_EOL;
    foreach ($t->transactionItems as $item) {
        echo "  - Amount: " . $item->amount . ", Category: " . ($item->category->name ?? 'NONE') . PHP_EOL;
    }

    echo "Comment: " . ($t->comment ?? 'N/A') . PHP_EOL;
}

echo PHP_EOL . "=== Checking for issues ===" . PHP_EOL;

// Count transactions without transaction items
$noItems = App\Models\Transaction::where('import_job_id', 234)
    ->doesntHave('transactionItems')
    ->count();
echo "Transactions without items: " . $noItems . PHP_EOL;

// Count transactions without proper config
$noConfig = App\Models\Transaction::where('import_job_id', 234)
    ->whereNull('config_id')
    ->count();
echo "Transactions without config: " . $noConfig . PHP_EOL;

// Check for any with zero amount items
$zeroAmounts = App\Models\TransactionItem::whereHas('transaction', function ($q) {
    $q->where('import_job_id', 234);
})->where('amount', 0)->count();
echo "Transaction items with zero amount: " . $zeroAmounts . PHP_EOL;

// Check the CSV file for total rows
$filePath = storage_path('app/' . $import->file_path);
$csvRowCount = 0;
if (file_exists($filePath)) {
    $handle = fopen($filePath, 'r');
    fgetcsv($handle); // skip header
    while (fgetcsv($handle) !== false) {
        $csvRowCount++;
    }
    fclose($handle);
    echo PHP_EOL . "CSV file has {$csvRowCount} data rows" . PHP_EOL;
    echo "Import processed {$import->processed_rows} rows" . PHP_EOL;
    echo "Created " . App\Models\Transaction::where('import_job_id', 234)->count() . " transactions" . PHP_EOL;
    echo "Difference: " . ($csvRowCount - App\Models\Transaction::where('import_job_id', 234)->count()) . " rows not imported" . PHP_EOL;
}

// Check if import has errors logged
if ($import->errors && count($import->errors) > 0) {
    echo PHP_EOL . "=== Import Errors ===" . PHP_EOL;
    foreach (array_slice($import->errors, 0, 10) as $error) {
        echo $error . PHP_EOL;
    }
    if (count($import->errors) > 10) {
        echo "... and " . (count($import->errors) - 10) . " more errors" . PHP_EOL;
    }
}

// Sample the CSV to see what accounts are failing
echo PHP_EOL . "=== Sampling CSV for account names ===" . PHP_EOL;
$filePath = storage_path('app/' . $import->file_path);
$handle = fopen($filePath, 'r');
$header = fgetcsv($handle);
$accountIndex = array_search('ACCOUNT', $header);
$accountsSeen = [];
$rowNum = 0;
while (($row = fgetcsv($handle)) !== false && $rowNum < 100) {
    $rowNum++;
    if ($accountIndex !== false && isset($row[$accountIndex])) {
        $acctName = $row[$accountIndex];
        if (!isset($accountsSeen[$acctName])) {
            $accountsSeen[$acctName] = 1;
        } else {
            $accountsSeen[$acctName]++;
        }
    }
}
fclose($handle);

echo "Unique accounts in first 100 rows:" . PHP_EOL;
foreach ($accountsSeen as $acctName => $count) {
    echo "  - \"{$acctName}\" ({$count} times)" . PHP_EOL;
}

echo PHP_EOL;
