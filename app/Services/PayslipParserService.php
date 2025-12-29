<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayslipParserService
{
    /**
     * Process a payslip JSON file (from unstructured data processor) and create transactions.
     * 
     * @param string $filePath Path to the JSON file
     * @param int $userId User ID
     * @param int $employmentAccountId The employment account to deposit to
     * @param \App\Models\ImportJob|null $import Optional import job for progress tracking
     * @return array ['processed' => int, 'created' => int, 'errors' => array]
     */
    public function processPayslipFile(string $filePath, int $userId, int $employmentAccountId, $import = null)
    {
        $errors = [];
        $created = 0;
        $processed = 0;

        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return ['processed' => 0, 'created' => 0, 'errors' => ['unable_to_read_file']];
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['processed' => 0, 'created' => 0, 'errors' => ['invalid_json' => json_last_error_msg()]];
            }

            // Validate employment account
            $employmentAccount = AccountEntity::where('id', $employmentAccountId)
                ->where('user_id', $userId)
                ->where('config_type', 'account')
                ->first();

            if (!$employmentAccount) {
                return ['processed' => 0, 'created' => 0, 'errors' => ['invalid_employment_account']];
            }

            // Extract payslip data from the first element
            $element = $data[0] ?? null;
            if (!$element || !isset($element['metadata']['text_as_html'])) {
                return ['processed' => 0, 'created' => 0, 'errors' => ['no_payslip_data_found']];
            }

            $html = $element['metadata']['text_as_html'];
            $payslipData = $this->parsePayslipHtml($html);

            if (empty($payslipData)) {
                return ['processed' => 0, 'created' => 0, 'errors' => ['failed_to_parse_payslip']];
            }

            // Determine pay period end date as transaction date
            $payDate = $payslipData['pay_period_end'] ?? null;
            if (!$payDate) {
                return ['processed' => 0, 'created' => 0, 'errors' => ['missing_pay_date']];
            }

            // Create deposit transaction with categorized items
            DB::transaction(function() use ($payslipData, $userId, $employmentAccount, $payDate, &$created) {
                $netPay = $payslipData['net_pay'] ?? 0;

                // Create transaction detail (deposit into employment account)
                $detail = TransactionDetailStandard::create([
                    'account_from_id' => null, // Employer is not tracked as account
                    'account_to_id' => $employmentAccount->id,
                ]);

                $depositType = TransactionType::where('name', 'deposit')->first();
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'config_type' => 'standard',
                    'config_id' => $detail->id,
                    'transaction_type_id' => $depositType->id ?? 2,
                    'date' => $payDate,
                    'comment' => 'Payslip: ' . ($payslipData['company'] ?? 'Unknown'),
                ]);

                // Get or create categories
                $salaryCategory = Category::firstOrCreate(
                    ['name' => 'Salary', 'user_id' => $userId],
                    ['active' => true, 'category_group_id' => null]
                );
                $taxCategory = Category::firstOrCreate(
                    ['name' => 'Income Tax', 'user_id' => $userId],
                    ['active' => true, 'category_group_id' => null]
                );
                $niCategory = Category::firstOrCreate(
                    ['name' => 'National Insurance', 'user_id' => $userId],
                    ['active' => true, 'category_group_id' => null]
                );
                $pensionCategory = Category::firstOrCreate(
                    ['name' => 'Pension Contribution', 'user_id' => $userId],
                    ['active' => true, 'category_group_id' => null]
                );
                $benefitsCategory = Category::firstOrCreate(
                    ['name' => 'Benefits', 'user_id' => $userId],
                    ['active' => true, 'category_group_id' => null]
                );

                // Add earnings as positive transaction items
                foreach ($payslipData['earnings'] as $earning) {
                    $category = $salaryCategory; // Default to salary
                    if (stripos($earning['description'], 'commission') !== false) {
                        $category = Category::firstOrCreate(
                            ['name' => 'Commission', 'user_id' => $userId],
                            ['active' => true, 'category_group_id' => null]
                        );
                    } elseif (stripos($earning['description'], 'bonus') !== false) {
                        $category = Category::firstOrCreate(
                            ['name' => 'Bonus', 'user_id' => $userId],
                            ['active' => true, 'category_group_id' => null]
                        );
                    }

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $employmentAccount->id,
                        'category_id' => $category->id,
                        'amount' => abs($earning['amount']),
                        'comment' => $earning['description'],
                    ]);
                }

                // Add deductions as negative transaction items
                foreach ($payslipData['deductions'] as $deduction) {
                    $category = $benefitsCategory; // Default category
                    
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $employmentAccount->id,
                        'category_id' => $category->id,
                        'amount' => -1 * abs($deduction['amount']),
                        'comment' => $deduction['description'],
                    ]);
                }

                // Add taxes as negative transaction items
                foreach ($payslipData['taxes'] as $tax) {
                    $category = $taxCategory;
                    if (stripos($tax['description'], 'NI') !== false || stripos($tax['description'], 'National Insurance') !== false) {
                        $category = $niCategory;
                    }

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'account_id' => $employmentAccount->id,
                        'category_id' => $category->id,
                        'amount' => -1 * abs($tax['amount']),
                        'comment' => $tax['description'],
                    ]);
                }

                $created++;
            });

            $processed++;

        } catch (\Exception $e) {
            Log::error('Payslip import error: ' . $e->getMessage(), ['file' => $filePath]);
            $errors[] = ['error' => $e->getMessage()];
        }

        return ['processed' => $processed, 'created' => $created, 'errors' => $errors];
    }

    /**
     * Parse HTML tables from payslip to extract structured data.
     */
    protected function parsePayslipHtml(string $html): array
    {
        $data = [
            'earnings' => [],
            'deductions' => [],
            'taxes' => [],
            'net_pay' => 0,
            'pay_period_end' => null,
            'company' => null,
        ];

        // Use DOMDocument to parse HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // Extract pay period end date from first table
        $firstTable = $xpath->query('//table[1]//td[contains(text(), "/")]')->item(0);
        if ($firstTable) {
            $dateText = trim($firstTable->textContent);
            try {
                $data['pay_period_end'] = Carbon::createFromFormat('d/m/Y', $dateText);
            } catch (\Exception $e) {
                // Try other formats
                $data['pay_period_end'] = Carbon::parse($dateText);
            }
        }

        // Extract company from first table
        $companyCell = $xpath->query('//table[1]//td[3]')->item(0);
        if ($companyCell) {
            $data['company'] = trim($companyCell->textContent);
        }

        // Extract Net Pay from summary table
        $netPayCell = $xpath->query('//table[2]//tr[td[text()="Current"]]/td[6]')->item(0);
        if ($netPayCell) {
            $data['net_pay'] = $this->parseAmount($netPayCell->textContent);
        }

        // Extract Earnings table
        $earningsRows = $xpath->query('//table[contains(.//th, "Earnings")]//tr[td]');
        foreach ($earningsRows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length >= 2) {
                $description = trim($cells->item(0)->textContent);
                $amountText = trim($cells->item($cells->length - 1)->textContent);
                
                // Skip summary rows
                if ($description === 'Earnings' || empty($description)) {
                    continue;
                }

                $amount = $this->parseAmount($amountText);
                if ($amount != 0) {
                    if ($amount > 0) {
                        $data['earnings'][] = ['description' => $description, 'amount' => $amount];
                    } else {
                        // Negative earnings are actually deductions taken from gross
                        $data['deductions'][] = ['description' => $description, 'amount' => abs($amount)];
                    }
                }
            }
        }

        // Extract Deductions table
        $deductionsRows = $xpath->query('//table[contains(.//th, "Deductions")]//tr[td]');
        foreach ($deductionsRows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length >= 2) {
                $description = trim($cells->item(0)->textContent);
                $amountText = trim($cells->item(1)->textContent);
                
                // Skip summary rows
                if ($description === 'Deductions' || empty($description)) {
                    continue;
                }

                $amount = $this->parseAmount($amountText);
                if ($amount != 0) {
                    $data['deductions'][] = ['description' => $description, 'amount' => abs($amount)];
                }
            }
        }

        // Extract Employee Taxes table
        $taxRows = $xpath->query('//table[contains(.//th, "Employee Taxes")]//tr[td]');
        foreach ($taxRows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length >= 2) {
                $description = trim($cells->item(0)->textContent);
                $amountText = trim($cells->item(1)->textContent);
                
                // Skip summary rows
                if ($description === 'Taxes' || empty($description)) {
                    continue;
                }

                $amount = $this->parseAmount($amountText);
                if ($amount != 0) {
                    $data['taxes'][] = ['description' => $description, 'amount' => abs($amount)];
                }
            }
        }

        return $data;
    }

    /**
     * Parse amount string like "7,360.89" or "-251.80" to float.
     */
    protected function parseAmount(string $text): float
    {
        $text = trim($text);
        $text = str_replace(',', '', $text);
        return floatval($text);
    }
}
