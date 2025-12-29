<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use App\Models\AccountEntity;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AmazonTransactionUploader
{
    private User $user;
    private AccountEntity $amazonAccount;
    private ?AccountEntity $defaultPayee;
    private ?Category $defaultCategory;
    private bool $needsRecalculation = false;
    
    public function __construct(User $user, AccountEntity $amazonAccount)
    {
        $this->user = $user;
        $this->amazonAccount = $amazonAccount;
        
        // Find or create default payee for Amazon purchases
        $this->defaultPayee = $this->findOrCreateAmazonPayee();
        
        // Get user's default category for shopping if available
        $this->defaultCategory = $user->categories()
            ->where('name', 'LIKE', '%shopping%')
            ->orWhere('name', 'LIKE', '%amazon%')
            ->orWhere('name', 'LIKE', '%online%')
            ->first();
    }

    /**
     * Process Amazon orders CSV file and create withdrawal transactions
     */
    public function processOrdersFile(string $filePath): array
    {
        $results = [
            'total' => 0,
            'processed' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'errors' => [],
        ];

        $orders = $this->parseCsv($filePath);
        
        // Group by Order ID to handle multi-item orders
        $groupedOrders = [];
        foreach ($orders as $order) {
            $orderId = $order['Order ID'];
            if (!isset($groupedOrders[$orderId])) {
                $groupedOrders[$orderId] = [];
            }
            $groupedOrders[$orderId][] = $order;
        }

        foreach ($groupedOrders as $orderId => $orderItems) {
            $results['total']++;
            
            try {
                // Use first item for transaction metadata
                $firstItem = $orderItems[0];
                
                // Check for duplicates
                if ($this->isDuplicate($orderId, $firstItem)) {
                    $results['duplicates']++;
                    $results['skipped']++;
                    continue;
                }

                // Create withdrawal transaction
                $this->createWithdrawalTransaction($orderItems);
                $results['processed']++;
                
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ];
                Log::error('Amazon order import failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }        
        // Trigger recalculation if any transactions were created
        if ($results['processed'] > 0) {
            $this->triggerRecalculation();
        }
        return $results;
    }

    /**
     * Process Amazon returns CSV file and create deposit transactions
     */
    public function processReturnsFile(string $filePath): array
    {
        $results = [
            'total' => 0,
            'processed' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'errors' => [],
        ];

        $returns = $this->parseCsv($filePath);

        foreach ($returns as $return) {
            $results['total']++;
            
            try {
                // Debug: log the keys we received
                if (!isset($return['OrderID'])) {
                    Log::error('Missing OrderID in return data', [
                        'keys' => array_keys($return),
                        'data' => $return,
                    ]);
                }
                
                $orderId = $return['OrderID'] ?? 'unknown';
                $reversalId = $return['ReversalID'] ?? 'unknown';
                
                // Check for duplicates
                if ($this->isRefundDuplicate($orderId, $reversalId)) {
                    $results['duplicates']++;
                    $results['skipped']++;
                    continue;
                }

                // Create deposit transaction
                $this->createDepositTransaction($return);
                $results['processed']++;
                
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'order_id' => $return['OrderID'] ?? 'unknown',
                    'reversal_id' => $return['ReversalID'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                Log::error('Amazon return import failed', [
                    'return' => $return,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Trigger recalculation if any transactions were created
        if ($results['processed'] > 0) {
            $this->triggerRecalculation();
        }

        return $results;
    }

    /**
     * Parse CSV file
     */
    private function parseCsv(string $filePath): array
    {
        $data = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            
            // Trim whitespace from headers (some CSVs have extra spaces)
            $header = array_map('trim', $header);
            
            // Remove UTF-8 BOM if present (Excel/Amazon often adds this)
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
            }
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($header) === count($row)) {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        return $data;
    }

    /**
     * Check if order already imported
     */
    private function isDuplicate(string $orderId, array $orderData): bool
    {
        $orderDate = Carbon::parse($orderData['Order Date']);
        
        return Transaction::where('user_id', $this->user->id)
            ->where('date', $orderDate->toDateString())
            ->where('comment', 'LIKE', "Amazon Order: {$orderId}%")
            ->exists();
    }

    /**
     * Check if refund already imported
     */
    private function isRefundDuplicate(string $orderId, string $reversalId): bool
    {
        return Transaction::where('user_id', $this->user->id)
            ->where('comment', 'LIKE', "Amazon Refund: {$orderId} - {$reversalId}%")
            ->exists();
    }

    /**
     * Create withdrawal transaction for Amazon order
     */
    private function createWithdrawalTransaction(array $orderItems): Transaction
    {
        return DB::transaction(function () use ($orderItems) {
            $firstItem = $orderItems[0];
            $orderId = $firstItem['Order ID'];
            $orderDate = Carbon::parse($firstItem['Order Date']);
            $currency = $firstItem['Currency'];
            
            // Calculate total from all items in this order
            $totalOwed = 0;
            foreach ($orderItems as $item) {
                $totalOwed += (float) $item['Total Owed'];
            }

            // Get withdrawal transaction type
            $transactionType = TransactionType::where('name', 'withdrawal')->firstOrFail();
            
            // Create transaction detail
            $transactionDetail = TransactionDetailStandard::create([
                'account_from_id' => $this->amazonAccount->id,
                'account_to_id' => $this->defaultPayee?->id,
                'amount_from' => abs($totalOwed),
                'amount_to' => abs($totalOwed),
            ]);

            // Create transaction
            $transaction = new Transaction([
                'user_id' => $this->user->id,
                'transaction_type_id' => $transactionType->id,
                'config_type' => 'standard',
                'date' => $orderDate,
                'comment' => $this->generateOrderComment($orderId, $orderItems),
                'schedule' => false,
                'budget' => false,
                'reconciled' => false,
            ]);
            
            $transaction->config()->associate($transactionDetail);
            $transaction->saveQuietly();
            $this->needsRecalculation = true;

            // Create transaction items (one per product)
            foreach ($orderItems as $item) {
                $itemAmount = (float) $item['Total Owed'];
                
                if ($itemAmount > 0) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'category_id' => $this->defaultCategory?->id,
                        'amount' => abs($itemAmount),
                        'comment' => $this->sanitizeProductName($item['Product Name']),
                    ]);
                }
            }

            return $transaction;
        });
    }

    /**
     * Create deposit transaction for Amazon refund
     */
    private function createDepositTransaction(array $returnData): Transaction
    {
        return DB::transaction(function () use ($returnData) {
            $orderId = $returnData['OrderID'];
            $reversalId = $returnData['ReversalID'];
            $refundDate = Carbon::parse($returnData['RefundCompletionDate']);
            $amount = abs((float) $returnData['AmountRefunded']);

            // Get deposit transaction type
            $transactionType = TransactionType::where('name', 'deposit')->firstOrFail();
            
            // Create transaction detail
            $transactionDetail = TransactionDetailStandard::create([
                'account_from_id' => $this->defaultPayee?->id,
                'account_to_id' => $this->amazonAccount->id,
                'amount_from' => $amount,
                'amount_to' => $amount,
            ]);

            // Create transaction
            $transaction = new Transaction([
                'user_id' => $this->user->id,
                'transaction_type_id' => $transactionType->id,
                'config_type' => 'standard',
                'date' => $refundDate,
                'comment' => "Amazon Refund: {$orderId} - {$reversalId}",
                'schedule' => false,
                'budget' => false,
                'reconciled' => false,
            ]);
            
            $transaction->config()->associate($transactionDetail);
            $transaction->saveQuietly();
            $this->needsRecalculation = true;

            // Create single transaction item for refund
            if ($this->defaultCategory) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'category_id' => $this->defaultCategory->id,
                    'amount' => $amount,
                    'comment' => 'Amazon Refund',
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Find or create Amazon payee
     */
    private function findOrCreateAmazonPayee(): ?AccountEntity
    {
        // Try to find existing Amazon payee
        $payee = $this->user->payees()
            ->where('name', 'LIKE', '%amazon%')
            ->first();

        if (!$payee) {
            // Create new Amazon payee
            try {
                $payee = AccountEntity::create([
                    'user_id' => $this->user->id,
                    'name' => 'Amazon.co.uk',
                    'config_type' => 'payee',
                    'active' => true,
                ]);
                
                // Create the payee config
                \App\Models\Payee::create([
                    'account_entity_id' => $payee->id,
                ]);
            } catch (\Exception $e) {
                Log::warning('Could not create Amazon payee', ['error' => $e->getMessage()]);
                return null;
            }
        }

        return $payee;
    }

    /**
     * Generate transaction comment from order items
     */
    private function generateOrderComment(string $orderId, array $orderItems): string
    {
        $itemCount = count($orderItems);
        $firstProduct = $this->sanitizeProductName($orderItems[0]['Product Name']);
        
        $comment = "Amazon Order: {$orderId}";
        
        if ($itemCount === 1) {
            $comment .= " - {$firstProduct}";
        } else {
            $comment .= " - {$firstProduct} + " . ($itemCount - 1) . " more";
        }
        
        return substr($comment, 0, 255); // Ensure it fits in comment field
    }

    /**
     * Sanitize product name for display
     */
    private function sanitizeProductName(string $productName): string
    {
        // Truncate very long product names
        if (strlen($productName) > 100) {
            return substr($productName, 0, 97) . '...';
        }
        return $productName;
    }
    
    /**
     * Trigger account balance recalculation after bulk import
     */
    private function triggerRecalculation(): void
    {
        if ($this->needsRecalculation) {
            dispatch(new \App\Jobs\CalculateAccountMonthlySummary(
                $this->user,
                'account_balance-fact',
                $this->amazonAccount
            ));
            $this->needsRecalculation = false;
        }
    }
}
