<?php

namespace App\Console\Commands;

use App\Http\Traits\CurrencyTrait;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('cache:clear-currencies {--user= : Clear cache for specific user ID}')]
#[Description('Clear currency cache for all users or a specific user')]
class ClearCurrencyCache extends Command
{
    use CurrencyTrait;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId) {
            // Clear for specific user
            Cache::forget($this->getCurrenciesCacheKey((int) $userId));
            $this->info("Currency cache cleared for user {$userId}");
        } else {
            // Clear for all users
            $count = 0;
            foreach (User::lazy() as $user) {
                Cache::forget($this->getCurrenciesCacheKey($user->id));
                $count++;
            }

            $this->info("Currency cache cleared for {$count} users");
        }

        return Command::SUCCESS;
    }
}
