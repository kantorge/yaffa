<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\DbDumper\Databases\MySql;

class DumpDemoDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sandbox:dump-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the demo (sandbox) database to a SQL file to facilitate making changes to the demo dataset';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // This command cannot be run if sandbox mode is not enabled
        if (!config('yaffa.sandbox_mode')) {
            $this->error('This command can only be run in sandbox mode.');
            return Command::FAILURE;
        }

        MySql::create()
            // Set the database connection details based on the Laravel configuration
            ->setHost(config('database.connections.mysql.host'))
            ->setDbName(config('database.connections.mysql.database'))
            ->setUserName(config('database.connections.mysql.username'))
            ->setPassword(config('database.connections.mysql.password'))
            // The migrations will take care of creating the tables, so we don't need to do that here
            ->doNotCreateTables()
            // Explicitly define the tables we need to have in the dump
            ->includeTables([
                'investments',
                'tags',
                'transaction_items_tags',
                'currencies',
                'transactions',
                'transaction_items',
                'categories',
                'flags',
                'transaction_schedules',
                'account_entity_category_preference',
                'account_groups',
                'accounts',
                'transaction_details_standard',
                'transaction_details_investment',
                'investment_groups',
                'account_entities',
                'payees',
                'received_mails',
            ])
            // Some additional flags to customize the dump
            ->addExtraOption('--order-by-primary')
            ->addExtraOption('--skip-compact')
            ->addExtraOption('--complete-insert')
            // The file will be written to storage/export.sql, overwriting any existing file
            ->dumpToFile(storage_path('export.sql'));

        return Command::SUCCESS;
    }
}
