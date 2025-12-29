<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\Investment;
use App\Models\AccountEntity;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

class InvestmentTransactionUploader
{
    private User $user;
    private array $config;
    private array $duplicateCheckConfig;
    
    public function __construct(User $user, array $config)
    {
        $this->user = $user;
        $this->config = $config;
        $this->setupDuplicateCheck();
    }

    public function processFile(string $filePath, string $source): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        $data = match($extension) {
            'csv' => $this->parseCsv($filePath),
            'xlsx' => $this->parseExcel($filePath),
            'json' => $this->parseJson($filePath),
            'yaml', 'yml' => $this->parseYaml($filePath),
            default => throw new \InvalidArgumentException("Unsupported file type: {$extension}")
        };

        return $this->processTransactions($data, $source);
    }

    public function parseYaml(string $filePath): array
    {
        $content = file_get_contents($filePath);
        return Yaml::parse($content);
    }

    public function parseCsv(string $filePath): array
    {
        $data = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }
        return $data;
    }

    public function parseJson(string $filePath): array
    {
        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }

    public function parseExcel(string $filePath): array
    {
        // Implementation would use PHPSpreadsheet
        // For now, simple implementation
        return [];
    }

    private function processTransactions(array $data, string $source): array
    {
        $results = [
            'total' => 0,
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
            'duplicates' => 0
        ];

        $transactions = $data['transactions'] ?? $data;
        $mapping = $data['mapping'] ?? $this->getDefaultMapping($source);
        
        foreach ($transactions as $rowIndex => $rawTransaction) {
            $results['total']++;
            
            try {
                $transaction = $this->mapTransaction($rawTransaction, $mapping, $rowIndex);
                
                if ($this->isDuplicate($transaction, $rowIndex)) {
                    $results['duplicates']++;
                    $results['skipped']++;
                    continue;
                }
                
                $this->createTransaction($transaction);
                $results['processed']++;
                
            } catch (\Exception $e) {
                $results['errors'][] = "Row {$rowIndex}: " . $e->getMessage();
                Log::error("Investment upload error", [
                    'row' => $rowIndex,
                    'data' => $rawTransaction,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    public function mapTransaction(array $rawData, array $mapping, int $rowIndex): array
    {
        $transaction = [
            'user_id' => $this->user->id,
            'date' => null,
            'transaction_type_id' => null,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'comment' => null,
            'config' => [
                'account_id' => null,
                'investment_id' => null,
                'quantity' => null,
                'price' => null,
                'commission' => null,
                'tax' => null,
                'dividend' => null,
            ],
            '_row_sequence' => $rowIndex,
        ];

        foreach ($mapping as $yamlField => $yafffaConfig) {
            $value = $this->extractValue($rawData, $yamlField, $yafffaConfig);
            
            if ($value !== null) {
                if (str_contains($yafffaConfig['target'], 'config.')) {
                    $configField = str_replace('config.', '', $yafffaConfig['target']);
                    $transaction['config'][$configField] = $value;
                    
                    // Debug logging for quantity
                    if ($configField === 'quantity') {
                        Log::info("Mapped quantity", [
                            'source_field' => $yamlField,
                            'raw_value' => $rawData[$yamlField] ?? 'missing',
                            'extracted_value' => $value,
                            'row' => $rawData['ID'] ?? $rowIndex
                        ]);
                    }
                } else {
                    $transaction[$yafffaConfig['target']] = $value;
                }
            }
        }

        // Set _symbol from ISIN (preferred) or Ticker as fallback
        if (!isset($transaction['_symbol'])) {
            $transaction['_symbol'] = $transaction['_isin'] ?? $transaction['_ticker'] ?? null;
        }

        // For Trading212: Clean up the 'Total' field mapping
        // The 'Total' column means different things based on transaction type
        if (isset($transaction['_transaction_type_name'])) {
            $normalizedType = strtolower(trim($transaction['_transaction_type_name']));
            
            // Extract base type (e.g., "Dividend (Ordinary)" -> "dividend")
            if (preg_match('/^([^(]+)/', $normalizedType, $matches)) {
                $normalizedType = trim($matches[1]);
            }
            
            // Only keep dividend field for actual dividend and interest transactions
            $dividendTypes = ['dividend', 'interest on cash', 'interest', 'deposit', 'withdrawal'];
            if (!in_array($normalizedType, $dividendTypes)) {
                // For buy/sell/transfer, Total is just informational - don't store as dividend
                $transaction['config']['dividend'] = 0.0;
            }
        }

        // Validate and transform required fields
        $this->validateAndTransformTransaction($transaction);
        
        return $transaction;
    }

    private function extractValue(array $data, string $field, array $config)
    {
        // Handle nested field access (e.g., "details.price")
        $value = $data;
        foreach (explode('.', $field) as $key) {
            $value = $value[$key] ?? null;
            if ($value === null) break;
        }

        // Return null for empty/null values
        if ($value === null || $value === '') {
            return null;
        }

        // Apply transformations
        if (isset($config['transform'])) {
            switch ($config['transform']) {
                case 'date':
                    return $this->parseDate($value);
                case 'float':
                    return (float) $value;
                case 'int':
                    return (int) $value;
                case 'divide_by_100':
                    return (float) $value / 100;
                case 'multiply_by_100':
                    return (float) $value * 100;
            }
        }

        // Apply custom function if provided
        if (isset($config['custom_function'])) {
            return call_user_func($config['custom_function'], $value, $data);
        }

        return $value;
    }

    private function parseDate(string $value): Carbon
    {
        // Try multiple date formats commonly found in financial exports
        $formats = [
            'Y-m-d H:i:s',      // 2025-10-27 02:11:14 (Trading212 standard)
            'd/m/Y H:i',        // 28/05/2025 00:13 (UK format with time)
            'd/m/Y',            // 28/05/2025 (UK format)
            'm/d/Y H:i',        // 05/28/2025 00:13 (US format with time)
            'm/d/Y',            // 05/28/2025 (US format)
            'Y-m-d',            // 2025-05-28 (ISO date)
            'd-m-Y H:i:s',      // 28-05-2025 00:13:00
            'd-m-Y',            // 28-05-2025
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback to Carbon's parser which handles many formats
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            throw new \Exception("Could not parse '{$value}': " . $e->getMessage());
        }
    }

    private function validateAndTransformTransaction(array &$transaction): void
    {
        // STEP 1: Resolve account by name or use default account (needed for cash investments)
        if (!$transaction['config']['account_id']) {
            if (isset($transaction['_account_name'])) {
                $account = AccountEntity::where('user_id', $this->user->id)
                    ->whereHasMorph('config', 'App\Models\Account', function($q) use ($transaction) {
                        $q->where('name', $transaction['_account_name']);
                    })
                    ->first();
                    
                if ($account) {
                    $transaction['config']['account_id'] = $account->id;
                }
            }
            
            // Use default account if still not set
            if (!$transaction['config']['account_id'] && isset($this->config['default_account_id'])) {
                $transaction['config']['account_id'] = $this->config['default_account_id'];
            }
            
            if (!$transaction['config']['account_id']) {
                throw new \Exception("Account not found and no default account specified");
            }
        }

        // STEP 2: Resolve transaction type by name with aliases (MUST happen before cash-only handling)
        if (!$transaction['transaction_type_id'] && isset($transaction['_transaction_type_name'])) {
            // Normalize to lowercase for comparison
            $normalizedType = strtolower(trim($transaction['_transaction_type_name']));
            
            // Extract the base type if it has parentheses (e.g., "Dividend (Ordinary)" -> "dividend")
            // This handles Trading212's format: "Dividend (Ordinary)", "Dividend (Property income distribution)", etc.
            if (preg_match('/^([^(]+)/', $normalizedType, $matches)) {
                $normalizedType = trim($matches[1]);
            }
            
            // Map common aliases to standard transaction type names
            $typeAliases = [
                'transfer in' => 'Add shares',
                'market buy' => 'Buy',
                'market sell' => 'Sell',
                'dividend' => 'Dividend', // Handles "Dividend (Ordinary)", "Dividend (Property income)", etc.
                'interest on cash' => 'Interest yield',
                'deposit' => 'Interest yield', // Use Interest yield for deposits (cash in)
                'withdrawal' => 'Interest yield', // Use Interest yield for withdrawals (cash out, will be negative)
            ];
            
            $typeName = $typeAliases[$normalizedType] ?? $transaction['_transaction_type_name'];
            
            $transactionType = TransactionType::where('name', $typeName)
                ->where('type', 'investment')
                ->first();
                
            if ($transactionType) {
                $transaction['transaction_type_id'] = $transactionType->id;
                
                // For cash-only transactions (interest on cash, deposit/withdrawal), mark them specially
                if (in_array($normalizedType, ['interest on cash', 'deposit', 'withdrawal'])) {
                    $transaction['_is_cash_only'] = true;
                }
            } else {
                throw new \Exception("Transaction type not found: " . $transaction['_transaction_type_name'] . " (mapped to: {$typeName})");
            }
        }

        // STEP 3: Handle cash-only transactions (Interest on cash, Deposit/Withdrawal)
        if (isset($transaction['_is_cash_only']) && $transaction['_is_cash_only']) {
            // Get currency and account info
            $currencyId = null;
            $accountId = $transaction['config']['account_id'] ?? $this->config['default_account_id'] ?? null;
            
            if (isset($transaction['_currency'])) {
                $currency = \App\Models\Currency::where('iso_code', $transaction['_currency'])->first();
                $currencyId = $currency?->id;
            }
            
            if (!$currencyId && $accountId) {
                $account = AccountEntity::find($accountId);
                if ($account && $account->config) {
                    $currencyId = $account->config->currency_id;
                }
            }
            
            if (!$currencyId) {
                $currencyId = $this->user->currency_id;
            }
            
            // For Trading212 interest transactions, look for account-specific uninvested cash investment
            // Pattern: "uninvested cash with [Account Name]"
            $cashInvestment = null;
            if ($accountId && strpos(strtolower($transaction['_transaction_type_name']), 'interest') !== false) {
                $account = AccountEntity::with('config')->find($accountId);
                if ($account) {
                    // Try to find existing uninvested cash investment for this account
                    $searchPatterns = [
                        'uninvested cash with ' . strtolower($account->name),
                        'uninvested cash with ' . strtolower($account->config->name ?? ''),
                    ];
                    
                    foreach ($searchPatterns as $pattern) {
                        $cashInvestment = Investment::where('user_id', $this->user->id)
                            ->whereRaw('LOWER(name) LIKE ?', ['%' . $pattern . '%'])
                            ->where('currency_id', $currencyId)
                            ->first();
                        if ($cashInvestment) break;
                    }
                    
                    // If not found, create it
                    if (!$cashInvestment) {
                        $investmentGroup = $this->user->investmentGroups()->first();
                        if (!$investmentGroup) {
                            $investmentGroup = \App\Models\InvestmentGroup::create([
                                'name' => 'Imported Investments',
                                'user_id' => $this->user->id,
                            ]);
                        }
                        
                        $accountName = $account->config->name ?? $account->name;
                        $cashInvestment = Investment::create([
                            'user_id' => $this->user->id,
                            'name' => 'uninvested cash with ' . $accountName,
                            'symbol' => strtoupper(substr($accountName, 0, 4)) . '.UI',
                            'isin' => null,
                            'currency_id' => $currencyId,
                            'investment_group_id' => $investmentGroup->id,
                            'active' => true,
                            'auto_update' => false,
                        ]);
                        
                        Log::info("Created uninvested cash investment: {$cashInvestment->name} (ID: {$cashInvestment->id})");
                    }
                }
            }
            
            // Fallback to generic CASH if still not found
            if (!$cashInvestment) {
                $cashInvestment = Investment::where('user_id', $this->user->id)
                    ->where('symbol', 'CASH')
                    ->where('currency_id', $currencyId)
                    ->first();
                    
                if (!$cashInvestment) {
                    $investmentGroup = $this->user->investmentGroups()->first();
                    if (!$investmentGroup) {
                        $investmentGroup = \App\Models\InvestmentGroup::create([
                            'name' => 'Imported Investments',
                            'user_id' => $this->user->id,
                        ]);
                    }
                    
                    $cashInvestment = Investment::create([
                        'user_id' => $this->user->id,
                        'name' => 'Cash (' . ($currency->iso_code ?? 'Unknown') . ')',
                        'symbol' => 'CASH',
                        'isin' => null,
                        'currency_id' => $currencyId,
                        'investment_group_id' => $investmentGroup->id,
                        'active' => true,
                        'auto_update' => false,
                    ]);
                    
                    Log::info("Created Cash placeholder investment: {$cashInvestment->name} (ID: {$cashInvestment->id})");
                }
            }
            
            $transaction['config']['investment_id'] = $cashInvestment->id;
            $transaction['config']['quantity'] = null; // Interest/cash transactions don't have quantity
            $transaction['config']['price'] = null; // Interest/cash transactions don't have price
            
            // For withdrawals, make the dividend negative
            if (strpos(strtolower($transaction['_transaction_type_name']), 'withdrawal') !== false) {
                $transaction['config']['dividend'] = -abs($transaction['config']['dividend'] ?? 0);
            }
        }
        
        // Resolve investment by symbol/ISIN, or auto-create if not found
        if (!$transaction['config']['investment_id'] && isset($transaction['_symbol'])) {
            $investment = Investment::where('user_id', $this->user->id)
                ->where(function($q) use ($transaction) {
                    $q->where('symbol', $transaction['_symbol'])
                      ->orWhere('isin', $transaction['_symbol']);
                })
                ->first();
            
            if (!$investment) {
                // Auto-create investment from available data
                // Get currency ID from transaction currency, default account, or user's base currency
                $currencyId = null;
                
                if (isset($transaction['_currency'])) {
                    $currency = \App\Models\Currency::where('iso_code', $transaction['_currency'])->first();
                    $currencyId = $currency?->id;
                }
                
                if (!$currencyId && isset($this->config['default_account_id'])) {
                    $defaultAccount = AccountEntity::find($this->config['default_account_id']);
                    if ($defaultAccount && $defaultAccount->config) {
                        $currencyId = $defaultAccount->config->currency_id;
                    }
                }
                
                if (!$currencyId) {
                    $currencyId = $this->user->currency_id;
                }
                
                if (!$currencyId) {
                    // Final fallback: get the first available currency (GBP preferred)
                    $currency = \App\Models\Currency::where('iso_code', 'GBP')
                        ->orWhere('iso_code', 'USD')
                        ->orWhere('iso_code', 'EUR')
                        ->first();
                    $currencyId = $currency?->id;
                }
                
                if (!$currencyId) {
                    throw new \Exception("No currency available for investment creation");
                }
                
                // Get or create a default investment group
                $investmentGroup = $this->user->investmentGroups()->first();
                if (!$investmentGroup) {
                    $investmentGroup = \App\Models\InvestmentGroup::create([
                        'name' => 'Imported Investments',
                        'user_id' => $this->user->id,
                    ]);
                    Log::info("Created default investment group: {$investmentGroup->name} (ID: {$investmentGroup->id})");
                }
                
                $investmentData = [
                    'user_id' => $this->user->id,
                    'name' => $transaction['_investment_name'] ?? $transaction['_symbol'],
                    'symbol' => $transaction['_ticker'] ?? $transaction['_symbol'],
                    'isin' => $transaction['_isin'] ?? null,
                    'currency_id' => $currencyId,
                    'investment_group_id' => $investmentGroup->id,
                    'active' => true,
                    'auto_update' => false,
                ];
                
                $investment = Investment::create($investmentData);
                
                Log::info("Auto-created investment: {$investment->name} (ID: {$investment->id})");
            }
            
            $transaction['config']['investment_id'] = $investment->id;
        }

        // Set defaults for optional config fields
        $transaction['config']['commission'] = $transaction['config']['commission'] ?? 0.0;
        $transaction['config']['tax'] = $transaction['config']['tax'] ?? 0.0;
        $transaction['config']['dividend'] = $transaction['config']['dividend'] ?? 0.0;

        // Validate required fields
        if (!$transaction['date']) {
            throw new \Exception("Date is required");
        }
        if (!$transaction['config']['investment_id']) {
            throw new \Exception("Investment ID is required");
        }
        if (!$transaction['config']['account_id']) {
            throw new \Exception("Account ID is required");
        }
        if (!$transaction['transaction_type_id']) {
            throw new \Exception("Transaction type ID is required");
        }
    }

    private function setupDuplicateCheck(): void
    {
        $this->duplicateCheckConfig = [
            'fields' => ['date', 'config.investment_id', 'config.account_id', 'config.quantity', 'config.price'],
            'tolerance' => 0.01, // For price comparisons
        ];
    }

    private function isDuplicate(array $transaction, int $rowIndex): bool
    {
        // Check against existing transactions in database
        $existing = Transaction::where('user_id', $this->user->id)
            ->where('date', $transaction['date'])
            ->whereHasMorph('config', TransactionDetailInvestment::class, function($q) use ($transaction) {
                $q->where('investment_id', $transaction['config']['investment_id'])
                  ->where('account_id', $transaction['config']['account_id']);
                  
                if (isset($transaction['config']['quantity'])) {
                    $q->where('quantity', $transaction['config']['quantity']);
                }
                
                if (isset($transaction['config']['price'])) {
                    $q->whereBetween('price', [
                        $transaction['config']['price'] - $this->duplicateCheckConfig['tolerance'],
                        $transaction['config']['price'] + $this->duplicateCheckConfig['tolerance']
                    ]);
                }
            })
            ->exists();

        if ($existing) {
            Log::info("Duplicate transaction found", [
                'row' => $rowIndex,
                'date' => $transaction['date'],
                'investment_id' => $transaction['config']['investment_id']
            ]);
            return true;
        }

        return false;
    }

    private function createTransaction(array $transactionData): Transaction
    {
        return DB::transaction(function () use ($transactionData) {
            // Log config data for debugging
            \Log::info('Creating TransactionDetailInvestment with config:', $transactionData['config']);
            // Create the investment config FIRST
            $config = TransactionDetailInvestment::create($transactionData['config']);

            // Then create the transaction with the config_id
            $transaction = Transaction::create([
                'user_id' => $transactionData['user_id'],
                'date' => $transactionData['date'],
                'transaction_type_id' => $transactionData['transaction_type_id'],
                'reconciled' => $transactionData['reconciled'],
                'schedule' => $transactionData['schedule'],
                'budget' => $transactionData['budget'],
                'comment' => $transactionData['comment'],
                'config_type' => 'investment',
                'config_id' => $config->id,
            ]);

            return $transaction;
        });
    }

    public function getDefaultMapping(string $source): array
    {
        return match($source) {
            'WiseAlpha' => [
                'date' => ['target' => 'date', 'transform' => 'date'],
                'type' => ['target' => '_transaction_type_name'],
                'bond_name' => ['target' => '_symbol'],
                'account' => ['target' => '_account_name'],
                'quantity' => ['target' => 'config.quantity', 'transform' => 'float'],
                'price' => ['target' => 'config.price', 'transform' => 'divide_by_100'],
                'commission' => ['target' => 'config.commission', 'transform' => 'float'],
                'description' => ['target' => 'comment'],
            ],
            'Trading212' => [
                'Action' => ['target' => '_transaction_type_name'],
                'Time' => ['target' => 'date', 'transform' => 'date'],
                'ISIN' => ['target' => '_isin'],
                'Ticker' => ['target' => '_ticker'],
                'Name' => ['target' => '_investment_name'],
                'No. of shares' => ['target' => 'config.quantity', 'transform' => 'float'],
                'Price / share' => ['target' => 'config.price', 'transform' => 'divide_by_100'],
                'Currency (Price / share)' => ['target' => '_currency'],
                'Total' => ['target' => 'config.dividend', 'transform' => 'float'],
                'Withholding tax' => ['target' => 'config.tax', 'transform' => 'float'],
                'Charge amount' => ['target' => 'config.commission', 'transform' => 'float'],
                'ID' => ['target' => 'comment'],
                'Notes' => ['target' => '_notes'],
            ],
            default => []
        };
    }
}