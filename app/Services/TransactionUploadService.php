<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\TransactionImportRule;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class TransactionUploadService
{
    protected $user;
    protected $config;
    protected $rules;

    public function __construct($user, $config = [])
    {
        $this->user = $user;
        $this->config = $config;
        $this->rules = null;
    }

    /**
     * Load import rules for the user and optionally specific account.
     */
    protected function loadRules($accountId = null)
    {
        if ($this->rules === null) {
            $query = TransactionImportRule::where('user_id', $this->user->id)
                ->where('active', true)
                ->orderBy('priority');

            if ($accountId) {
                // Get rules for this specific account OR global rules (null account_id)
                $query->where(function ($q) use ($accountId) {
                    $q->whereNull('account_id')
                        ->orWhere('account_id', $accountId);
                });
            } else {
                // Only global rules
                $query->whereNull('account_id');
            }

            $this->rules = $query->get();
        }

        return $this->rules;
    }

    /**
     * Find matching import rule for a description.
     */
    public function findMatchingRule(string $description, $accountId = null)
    {
        $rules = $this->loadRules($accountId);

        foreach ($rules as $rule) {
            if ($rule->matches($description)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Get all user's accounts (not payees) for account selection.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserAccounts()
    {
        return $this->user->accounts()
            ->with('config.accountGroup')
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'alias' => $account->alias,
                'group' => $account->config->accountGroup->name ?? null,
            ]);
    }

    /**
     * Match a MoneyHub account name against account aliases.
     * Returns the AccountEntity ID if found, null otherwise.
     * @param string $moneyHubAccountName
     * @return int|null
     */
    public function matchAccountByAlias($moneyHubAccountName)
    {
        if (empty($moneyHubAccountName)) {
            return null;
        }

        // Query AccountEntity directly (which has the alias field) for both accounts and investments
        $accountEntities = AccountEntity::where('user_id', $this->user->id)
            ->whereIn('config_type', ['account', 'investment'])
            ->whereNotNull('alias')
            ->where('alias', '!=', '')
            ->get();

        foreach ($accountEntities as $entity) {
            // Check if the MoneyHub account name matches any alias (aliases are separated by newlines)
            $aliases = array_map('trim', explode("\n", $entity->alias));
            foreach ($aliases as $alias) {
                if (strcasecmp($alias, mb_trim($moneyHubAccountName)) === 0) {
                    return $entity->id;
                }
            }
        }

        return null;
    }

    /**
     * Match or create a payee based on description.
     * Returns the AccountEntity (Payee) ID.
     * @param string $description
     * @param int|null $categoryId
     * @return int
     */
    public function matchOrCreatePayee($description, $categoryId = null)
    {
        if (empty($description)) {
            $description = 'Unknown';
        }

        $description = mb_trim($description);

        // First try to match against existing payee import_alias
        $payees = $this->user->payees()->get();

        foreach ($payees as $payee) {
            if (empty($payee->alias)) {
                continue;
            }

            // Check if description matches any alias
            $aliases = array_map('trim', explode("\n", $payee->alias));
            foreach ($aliases as $alias) {
                if (strcasecmp($alias, $description) === 0) {
                    return $payee->id;
                }
            }
        }

        // Try exact name match
        $payee = $this->user->payees()
            ->where('name', $description)
            ->first();

        if ($payee) {
            return $payee->id;
        }

        // Create new payee
        $payeeConfig = \App\Models\Payee::create([
            'category_id' => $categoryId,
        ]);

        $payeeEntity = AccountEntity::create([
            'name' => $description,
            'active' => true,
            'config_type' => 'payee',
            'config_id' => $payeeConfig->id,
            'user_id' => $this->user->id,
        ]);

        Log::info("Created new payee: {$description} (ID: {$payeeEntity->id})");

        return $payeeEntity->id;
    }

    /**
     * Match or create a category based on name and optional group.
     * Returns the Category ID.
     * @param string|null $categoryName
     * @param string|null $categoryGroupName
     * @return int|null
     */
    public function matchOrCreateCategory($categoryName, $categoryGroupName = null)
    {
        if (empty($categoryName)) {
            return null;
        }

        $categoryName = mb_trim($categoryName);
        $parentId = null;

        // If we have a category group, find or create the parent category first
        if (!empty($categoryGroupName)) {
            $categoryGroupName = mb_trim($categoryGroupName);

            // Try to find existing parent category
            $parentCategory = $this->user->categories()
                ->where('name', $categoryGroupName)
                ->whereNull('parent_id') // Top-level category
                ->first();

            if (!$parentCategory) {
                // Create parent category
                $parentCategory = Category::create([
                    'name' => $categoryGroupName,
                    'parent_id' => null,
                    'user_id' => $this->user->id,
                ]);
                Log::info("Created new parent category: {$categoryGroupName} (ID: {$parentCategory->id})");
            }

            $parentId = $parentCategory->id;
        }

        // Now find or create the actual category
        $query = $this->user->categories()
            ->where('name', $categoryName);

        if ($parentId !== null) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        $category = $query->first();

        if ($category) {
            return $category->id;
        }

        // Create new category
        $category = Category::create([
            'name' => $categoryName,
            'parent_id' => $parentId,
            'user_id' => $this->user->id,
        ]);

        Log::info("Created new category: {$categoryName}" .
                  ($categoryGroupName ? " (Parent: {$categoryGroupName})" : "") .
                  " (ID: {$category->id})");

        return $category->id;
    }

    /**
     * Parse a MoneyHub CSV file and return mapped transactions.
     * @param string $filePath
     * @return array
     */
    public function parseMoneyHubCsv($filePath)
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($header, $row);
            }
            fclose($handle);
        }
        return $this->mapMoneyHubRows($rows);
    }

    /**
     * Map MoneyHub CSV rows to internal transaction structure.
     * @param array $rows
     * @return array
     */
    public function mapMoneyHubRows(array $rows)
    {
        $mapped = [];
        foreach ($rows as $row) {
            // Parse date - MoneyHub may export in different formats
            $date = null;
            if (isset($row['DATE'])) {
                // Try ISO format first (YYYY-MM-DD)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['DATE'])) {
                    $date = $row['DATE'];
                }
                // Try d/m/Y format (04/01/2026)
                elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $row['DATE'])) {
                    $date = Carbon::createFromFormat('d/m/Y', $row['DATE'])->format('Y-m-d');
                }
                // Try m/d/Y format (01/04/2026)
                elseif (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $row['DATE'])) {
                    $date = Carbon::createFromFormat('m/d/Y', $row['DATE'])->format('Y-m-d');
                }
                // Fallback: let Carbon try to parse it
                else {
                    try {
                        $date = Carbon::parse($row['DATE'])->format('Y-m-d');
                    } catch (Exception $e) {
                        Log::warning('Failed to parse date: ' . $row['DATE']);
                    }
                }
            }

            $mapped[] = [
                'date' => $date,
                'amount' => $row['AMOUNT'] ?? null,
                'description' => $row['DESCRIPTION'] ?? null,
                'category' => $row['CATEGORY'] ?? null,
                'category_group' => $row['CATEGORY GROUP'] ?? null,
                'account' => $row['ACCOUNT'] ?? null,
                'to_account' => $row['TO ACCOUNT'] ?? null,
                'project' => $row['PROJECT'] ?? null,
                // Add more mappings/transformations as needed
            ];
        }
        return $mapped;
    }
}
