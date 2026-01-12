<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCurrencyCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:clear-currencies {--user= : Clear cache for specific user ID}';

    /**
     * The console command description.
     */
    protected $description = 'Clear currency cache for all users or a specific user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId) {
            // Clear for specific user
            Cache::forget("currencies_user_{$userId}");
            $this->info("Currency cache cleared for user {$userId}");
        } else {
            // Clear for all users
            $count = 0;
            foreach (User::lazy() as $user) {
                Cache::forget("currencies_user_{$user->id}");
                $count++;
            }

            $this->info("Currency cache cleared for {$count} users");
        }

        return Command::SUCCESS;
    }
}
