<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionType;
use App\Services\NlpService;
use App\Services\TransactionService;
use Illuminate\Console\Command;

class IdentifyTransferTransactions extends Command
{
    protected $signature = 'transactions:identify-transfers
                            {--user= : Specific user ID to check}
                            {--confidence=0.7 : Minimum confidence threshold (0.0-1.0)}
                            {--convert : Actually convert the transactions (default: dry-run)}
                            {--limit=100 : Maximum transactions to process}';

    protected $description = 'Identify deposits/withdrawals that should be transfers using NLP';

    private NlpService $nlpService;
    private TransactionService $transactionService;

    public function __construct(NlpService $nlpService, TransactionService $transactionService)
    {
        parent::__construct();
        $this->nlpService = $nlpService;
        $this->transactionService = $transactionService;
    }

    public function handle(): int
    {
        if (!$this->nlpService->isAvailable()) {
            $this->error('NLP service is not available. Make sure the nlp-service container is running.');
            $this->info('Start it with: docker-compose up -d nlp-service');
            return self::FAILURE;
        }

        $confidence = (float) $this->option('confidence');
        $userId = $this->option('user');
        $shouldConvert = $this->option('convert');
        $limit = (int) $this->option('limit');

        if (!$shouldConvert) {
            $this->info('Running in DRY-RUN mode. Use --convert to actually convert transactions.');
        }

        // Get withdrawal and deposit type IDs
        $withdrawalType = TransactionType::where('name', 'withdrawal')->first();
        $depositType = TransactionType::where('name', 'deposit')->first();
        $transferType = TransactionType::where('name', 'transfer')->first();

        if (!$withdrawalType || !$depositType || !$transferType) {
            $this->error('Could not find required transaction types');
            return self::FAILURE;
        }

        // Get transactions to check
        $query = Transaction::with(['accountEntity', 'config'])
            ->whereIn('transaction_type_id', [$withdrawalType->id, $depositType->id]);

        if ($userId) {
            $query->whereHas('accountEntity', fn($q) => $q->where('user_id', $userId));
        }

        $transactions = $query->limit($limit)->get();

        if ($transactions->isEmpty()) {
            $this->info('No withdrawal/deposit transactions found to check.');
            return self::SUCCESS;
        }

        $this->info("Analyzing {$transactions->count()} transaction(s)...");
        $this->newLine();

        $potentialTransfers = [];

        $progressBar = $this->output->createProgressBar($transactions->count());

        foreach ($transactions as $transaction) {
            $payeeName = $transaction->accountEntity?->name ?? '';
            $transactionTypeName = $transaction->transactionType->name ?? '';
            $description = $transaction->config?->description ?? '';

            if (empty($payeeName)) {
                $progressBar->advance();
                continue;
            }

            // Call NLP service to classify
            $result = $this->nlpService->classifyTransfer(
                $payeeName,
                $transactionTypeName,
                $description
            );

            if ($result && $result['is_transfer'] && $result['confidence'] >= $confidence) {
                $potentialTransfers[] = [
                    'transaction' => $transaction,
                    'result' => $result,
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if (empty($potentialTransfers)) {
            $this->info('No transactions identified as transfers.');
            return self::SUCCESS;
        }

        $this->info('Found ' . count($potentialTransfers) . ' transaction(s) that should be transfers:');
        $this->newLine();

        foreach ($potentialTransfers as $item) {
            $transaction = $item['transaction'];
            $result = $item['result'];

            $this->line("Transaction #{$transaction->id}");
            $this->line("  Date: {$transaction->date}");
            $this->line("  Type: <fg=yellow>{$transaction->transactionType->name}</>");
            $this->line("  Payee: <fg=cyan>{$transaction->accountEntity->name}</>");
            $this->line("  Confidence: <fg=green>{$result['confidence']}</>");
            $this->line("  Matched Patterns: " . implode(', ', $result['matched_patterns']));

            if ($shouldConvert) {
                if ($this->confirm('Convert this transaction to a transfer?', false)) {
                    $this->convertToTransfer($transaction, $transferType->id);
                    $this->info('  ✓ Converted to transfer');
                } else {
                    $this->info('  Skipped');
                }
            }

            $this->newLine();
        }

        if (!$shouldConvert) {
            $this->info('To actually convert these transactions, run with --convert flag');
        }

        return self::SUCCESS;
    }

    /**
     * Convert a transaction to a transfer
     * Note: This is a simplified conversion. You may need to handle more complex cases.
     */
    private function convertToTransfer(Transaction $transaction, int $transferTypeId): void
    {
        // Delete any transaction items (transfers don't have items)
        $transaction->transactionItems()->delete();

        // Update transaction type
        $transaction->update([
            'transaction_type_id' => $transferTypeId,
        ]);

        // Note: You may need to update the config relationship
        // and set up the account_to/account_from properly
        // This is a basic implementation
    }
}
