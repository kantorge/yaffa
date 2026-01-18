<?php

namespace App\Console\Commands;

use App\Models\AccountEntity;
use Illuminate\Console\Command;

class AddAccountAlias extends Command
{
    protected $signature = 'yaffa:add-account-alias
                            {account : The YAFFA account name or ID}
                            {alias : The alias to add (e.g., Moneyhub account name)}
                            {--user=1 : User ID}';

    protected $description = 'Add an alias to an account for import matching';

    public function handle()
    {
        $accountIdentifier = $this->argument('account');
        $newAlias = $this->argument('alias');
        $userId = $this->option('user');

        // Find account by ID or name
        if (is_numeric($accountIdentifier)) {
            $account = AccountEntity::where('user_id', $userId)->find($accountIdentifier);
        } else {
            $account = AccountEntity::where('user_id', $userId)
                ->where('name', $accountIdentifier)
                ->first();
        }

        if (!$account) {
            $this->error("Account not found: {$accountIdentifier}");
            $this->info("Tip: Search for accounts with: php artisan tinker");
            $this->info("     App\\Models\\AccountEntity::where('user_id', {$userId})->get(['id', 'name'])");
            return 1;
        }

        // Get existing aliases
        $existingAliases = $account->alias ? explode("\n", mb_trim($account->alias)) : [];

        // Check if alias already exists
        if (in_array($newAlias, $existingAliases)) {
            $this->warn("Alias '{$newAlias}' already exists for account '{$account->name}'");
            return 0;
        }

        // Add new alias
        $existingAliases[] = $newAlias;
        $account->alias = implode("\n", $existingAliases);
        $account->save();

        $this->info("✓ Added alias '{$newAlias}' to account '{$account->name}' (ID: {$account->id})");
        $this->line("  All aliases: " . implode(' | ', $existingAliases));

        return 0;
    }
}
