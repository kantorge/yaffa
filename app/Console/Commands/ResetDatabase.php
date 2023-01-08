<?php

namespace App\Console\Commands;

use Artisan;
use Illuminate\Console\Command;

class ResetDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset app database to an initial state to remove visitor modifications';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Artisan::call('down');
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);
        Artisan::call('app:investment-prices:get');
        Artisan::call('up');

        return Command::SUCCESS;
    }
}
