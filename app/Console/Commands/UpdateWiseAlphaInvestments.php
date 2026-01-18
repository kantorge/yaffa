<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionSchedule;
use App\Models\TransactionType;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class UpdateWiseAlphaInvestments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:update-wisealpha {csv_file? : Path to the CSV file (optional, will download from API if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update WiseAlpha investments from CSV: prices, dividend schedules, and maturity schedules';

    private const WISEALPHA_API_URL = 'https://www.wisealpha.com/pubapi/v1/products/?format=csv&maturity__gte=2020&maturity__lte=2086&yield_to_maturity__gte=0.25&yield_to_maturity__lte=50&current_yield__gte=0.01&current_yield__lte=50&coupon__gte=0.01&coupon__lte=14';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $csvFile = $this->argument('csv_file');
        $downloadedFile = false;

        // If no CSV file provided, download from API
        if (!$csvFile) {
            $this->info("Downloading CSV from WiseAlpha API...");

            try {
                $client = new Client([
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'text/csv,application/csv,text/plain',
                    ],
                    'timeout' => 30,
                    'verify' => false, // Disable SSL verification (only for development)
                ]);

                $response = $client->get(self::WISEALPHA_API_URL);
                $csvContent = $response->getBody()->getContents();

                if (empty($csvContent)) {
                    $this->error("Downloaded CSV is empty");
                    return 1;
                }

                // Save to temp file
                $csvFile = storage_path('app/wisealpha_temp_' . date('Ymd_His') . '.csv');
                file_put_contents($csvFile, $csvContent);
                $downloadedFile = true;

                $this->info("Downloaded successfully");
            } catch (Exception $e) {
                $this->error("Error downloading CSV: " . $e->getMessage());
                return 1;
            }
        }

        if (!file_exists($csvFile)) {
            $this->error("CSV file not found: {$csvFile}");
            return 1;
        }

        $this->info("Loading CSV file: {$csvFile}");

        // Open and parse CSV
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $this->error("Failed to open CSV file");
            return 1;
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            $this->error("Failed to read CSV headers");
            fclose($handle);
            return 1;
        }

        $updated = 0;
        $skipped = 0;
        $pricesCreated = 0;
        $dividendSchedulesCreated = 0;
        $dividendSchedulesUpdated = 0;
        $maturitySchedulesCreated = 0;
        $maturitySchedulesUpdated = 0;

        $today = Carbon::today();

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            $isin = $data['ISIN'] ?? null;
            $buyPrice = $data['Buy Price'] ?? null;
            $maturity = $data['Maturity'] ?? null;
            $nextPayment = $data['Next Payment'] ?? null;
            $coupon = $data['Coupon'] ?? null;

            if (!$isin) {
                continue;
            }

            // Find investment by ISIN
            $investment = Investment::where('isin', $isin)->first();

            if (!$investment) {
                $skipped++;
                continue;
            }

            if ($this->option('verbose')) {
                $this->line("Processing: {$investment->name} (ISIN: {$isin})");
            }

            DB::transaction(function () use (
                $investment,
                $buyPrice,
                $maturity,
                $nextPayment,
                $coupon,
                $today,
                &$pricesCreated,
                &$dividendSchedulesCreated,
                &$dividendSchedulesUpdated,
                &$maturitySchedulesCreated,
                &$maturitySchedulesUpdated
            ) {
                // 1. Create price update (Buy Price / 100)
                if ($buyPrice && is_numeric($buyPrice)) {
                    $price = floatval($buyPrice) / 100;

                    // Check if price already exists for today
                    $existingPrice = InvestmentPrice::where('investment_id', $investment->id)
                        ->where('date', $today)
                        ->first();

                    if (!$existingPrice) {
                        InvestmentPrice::create([
                            'investment_id' => $investment->id,
                            'date' => $today,
                            'price' => $price,
                        ]);
                        $pricesCreated++;
                    }
                }

                // 2. Update/create dividend schedules
                if ($nextPayment && $coupon && is_numeric($coupon)) {
                    $nextPaymentDate = Carbon::parse($nextPayment);
                    $couponAmount = floatval($coupon) / 100;

                    // Find existing dividend schedule for this investment
                    $dividendType = TransactionType::where('name', 'Dividend')->first();
                    if ($dividendType) {
                        $existingDividendSchedule = Transaction::join('transaction_details_investment', function ($join) use ($investment) {
                            $join->on('transactions.config_id', '=', 'transaction_details_investment.id')
                                ->where('transaction_details_investment.investment_id', '=', $investment->id);
                        })
                            ->where('transactions.user_id', $investment->user_id)
                            ->where('transactions.schedule', true)
                            ->where('transactions.transaction_type_id', $dividendType->id)
                            ->where('transactions.config_type', 'investment')
                            ->select('transactions.*')
                            ->first();

                        if ($existingDividendSchedule) {
                            if ($this->option('verbose')) {
                                $this->line("  - Dividend schedule exists, updating");
                            }
                            // Update existing schedule
                            $existingDividendSchedule->transactionSchedule()->update([
                                'next_date' => $nextPaymentDate,
                            ]);

                            // Update dividend amount in config
                            $existingDividendSchedule->config()->update([
                                'dividend' => $couponAmount,
                            ]);

                            $dividendSchedulesUpdated++;
                        } else {
                            // Find the most recent BUY transaction to get the account
                            $buyType = TransactionType::where('name', 'Buy')->first();
                            if ($buyType) {
                                $recentBuyTransaction = Transaction::join('transaction_details_investment', function ($join) use ($investment) {
                                    $join->on('transactions.config_id', '=', 'transaction_details_investment.id')
                                        ->where('transaction_details_investment.investment_id', '=', $investment->id);
                                })
                                    ->where('transactions.user_id', $investment->user_id)
                                    ->where('transactions.transaction_type_id', $buyType->id)
                                    ->where('transactions.config_type', 'investment')
                                    ->where('transactions.schedule', false)
                                    ->orderBy('transactions.date', 'desc')
                                    ->select('transaction_details_investment.account_id')
                                    ->first();

                                if ($recentBuyTransaction && $recentBuyTransaction->account_id) {
                                    if ($this->option('verbose')) {
                                        $this->line("  - Creating dividend schedule with account {$recentBuyTransaction->account_id}");
                                    }
                                    // Create new dividend schedule transaction
                                    $config = TransactionDetailInvestment::create([
                                        'account_id' => $recentBuyTransaction->account_id,
                                        'investment_id' => $investment->id,
                                        'dividend' => $couponAmount,
                                        'quantity' => 1,
                                    ]);

                                    $transaction = Transaction::create([
                                        'user_id' => $investment->user_id,
                                        'date' => $nextPaymentDate,
                                        'transaction_type_id' => $dividendType->id,
                                        'schedule' => true,
                                        'reconciled' => false,
                                        'budget' => false,
                                        'comment' => "WiseAlpha dividend for {$investment->name}",
                                        'config_type' => 'investment',
                                        'config_id' => $config->id,
                                    ]);

                                    // Calculate cashflow: dividend - tax - commission (for dividend: just the dividend amount)
                                    $transactionService = new \App\Services\TransactionService();
                                    $transaction->currency_id = $transactionService->getTransactionCurrencyId($transaction);
                                    $transaction->cashflow_value = $transactionService->getTransactionCashFlow($transaction);
                                    $transaction->saveQuietly();

                                    // Create schedule (semi-annual payments)
                                    TransactionSchedule::create([
                                        'transaction_id' => $transaction->id,
                                        'start_date' => $nextPaymentDate,
                                        'next_date' => $nextPaymentDate,
                                        'frequency' => 'MONTHLY',
                                        'interval' => 6,
                                        'automatic_recording' => false,
                                        'active' => true,
                                    ]);

                                    $dividendSchedulesCreated++;
                                }
                            }
                        }
                    }
                }

                // 3. Update/create maturity sell schedules
                if ($maturity) {
                    $maturityDate = Carbon::parse($maturity);

                    $sellType = TransactionType::where('name', 'Sell')->first();
                    if ($sellType) {
                        // Find existing maturity schedule for this investment
                        $existingMaturitySchedule = Transaction::join('transaction_details_investment', function ($join) use ($investment) {
                            $join->on('transactions.config_id', '=', 'transaction_details_investment.id')
                                ->where('transaction_details_investment.investment_id', '=', $investment->id);
                        })
                            ->join('transaction_schedules', 'transactions.id', '=', 'transaction_schedules.transaction_id')
                            ->where('transactions.user_id', $investment->user_id)
                            ->where('transactions.schedule', true)
                            ->where('transactions.transaction_type_id', $sellType->id)
                            ->where('transactions.config_type', 'investment')
                            ->select('transactions.*')
                            ->first();

                        if ($existingMaturitySchedule) {
                            if ($this->option('verbose')) {
                                $this->line("  - Maturity schedule exists, updating");
                            }
                            // Update existing schedule
                            $existingMaturitySchedule->update([
                                'date' => $maturityDate,
                            ]);

                            $existingMaturitySchedule->transactionSchedule()->update([
                                'next_date' => $maturityDate,
                                'end_date' => $maturityDate,
                            ]);

                            // Ensure price is 1
                            $existingMaturitySchedule->config()->update([
                                'price' => 1.00,
                            ]);

                            $maturitySchedulesUpdated++;
                        } else {
                            // Find the most recent BUY transaction to get the account
                            $buyType = TransactionType::where('name', 'Buy')->first();
                            if ($buyType) {
                                $recentBuyTransaction = Transaction::join('transaction_details_investment', function ($join) use ($investment) {
                                    $join->on('transactions.config_id', '=', 'transaction_details_investment.id')
                                        ->where('transaction_details_investment.investment_id', '=', $investment->id);
                                })
                                    ->where('transactions.user_id', $investment->user_id)
                                    ->where('transactions.transaction_type_id', $buyType->id)
                                    ->where('transactions.config_type', 'investment')
                                    ->where('transactions.schedule', false)
                                    ->orderBy('transactions.date', 'desc')
                                    ->select('transaction_details_investment.account_id')
                                    ->first();

                                if ($recentBuyTransaction && $recentBuyTransaction->account_id) {
                                    // Get current quantity
                                    $currentQuantity = $investment->getCurrentQuantity();

                                    if ($this->option('verbose')) {
                                        $this->line("  - Maturity: Recent BUY found (Account: {$recentBuyTransaction->account_id}), Qty: {$currentQuantity}");
                                    }

                                    if ($currentQuantity > 0) {
                                        if ($this->option('verbose')) {
                                            $this->line("  - Creating maturity schedule");
                                        }
                                        // Create new maturity sell transaction
                                        $config = TransactionDetailInvestment::create([
                                            'account_id' => $recentBuyTransaction->account_id,
                                            'investment_id' => $investment->id,
                                            'price' => 1.00,
                                            'quantity' => $currentQuantity,
                                        ]);

                                        $transaction = Transaction::create([
                                            'user_id' => $investment->user_id,
                                            'date' => $maturityDate,
                                            'transaction_type_id' => $sellType->id,
                                            'schedule' => true,
                                            'reconciled' => false,
                                            'budget' => false,
                                            'comment' => "WiseAlpha maturity for {$investment->name}",
                                            'config_type' => 'investment',
                                            'config_id' => $config->id,
                                        ]);

                                        // Calculate cashflow: amount_multiplier * price * quantity (for sell: positive value)
                                        $transactionService = new \App\Services\TransactionService();
                                        $transaction->currency_id = $transactionService->getTransactionCurrencyId($transaction);
                                        $transaction->cashflow_value = $transactionService->getTransactionCashFlow($transaction);
                                        $transaction->saveQuietly();

                                        // Create one-time schedule
                                        TransactionSchedule::create([
                                            'transaction_id' => $transaction->id,
                                            'start_date' => $maturityDate,
                                            'next_date' => $maturityDate,
                                            'end_date' => $maturityDate,
                                            'frequency' => 'DAILY',
                                            'interval' => 1,
                                            'count' => 1,
                                            'automatic_recording' => false,
                                            'active' => true,
                                        ]);

                                        $maturitySchedulesCreated++;
                                    }
                                }
                            }
                        }
                    }
                }
            });

            $updated++;
        }

        fclose($handle);

        // Clean up downloaded temp file
        if ($downloadedFile && file_exists($csvFile)) {
            unlink($csvFile);
        }

        $this->info("Processing complete!");
        $this->info("Investments updated: {$updated}");
        $this->info("Investments skipped (not found): {$skipped}");
        $this->info("Prices created: {$pricesCreated}");
        $this->info("Dividend schedules created: {$dividendSchedulesCreated}");
        $this->info("Dividend schedules updated: {$dividendSchedulesUpdated}");
        $this->info("Maturity schedules created: {$maturitySchedulesCreated}");
        $this->info("Maturity schedules updated: {$maturitySchedulesUpdated}");

        return 0;
    }
}
