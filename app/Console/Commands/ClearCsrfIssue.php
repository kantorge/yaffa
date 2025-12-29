<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearCsrfIssue extends Command
{
    protected $signature = 'csrf:clear-issue';
    protected $description = 'Instructions to fix CSRF cookie conflicts';

    public function handle(): int
    {
        $this->info('To fix the CSRF token mismatch issue:');
        $this->newLine();
        $this->warn('The issue is caused by conflicting secure/non-secure cookies.');
        $this->newLine();
        $this->info('SOLUTION:');
        $this->line('1. Open your browser (Firefox)');
        $this->line('2. Press F12 to open Developer Tools');
        $this->line('3. Go to Storage tab → Cookies → http://jaffa.test');
        $this->line('4. Delete ALL cookies (especially XSRF-TOKEN)');
        $this->line('5. Refresh the page and try again');
        $this->newLine();
        $this->info('Alternative: Use Incognito/Private window');
        
        return Command::SUCCESS;
    }
}
