<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(TransactionTypeSeeder::class, 'db');
        $this->call(AccountGroupTableSeeder::class, 'db');
        $this->call(CurrencyTableSeeder::class, 'db');
        $this->call(CurrencyRateSeeder::class, 'random');
        $this->call(AccountSeeder::class, 'db');
        $this->call(CategoryTableSeeder::class, 'db');
        $this->call(PayeeSeeder::class, 'db');
        $this->call(InvestmentGroupTableSeeder::class, 'db');
        $this->call(InvestmentTableSeeder::class, 'db');
        $this->call(InvestmentPriceSeeder::class, 'db');
        $this->call(TagTableSeeder::class, 'db');
        $this->call(TransactionSeeder::class, 'db');
    }

    public function call($class, $extra = null) {
        if (isset($this->command)) {
            $this->command->getOutput()->writeln("<fg=yellow>Seeding:</> $class");
            $start = microtime(true);
        }

        $this->resolve($class)->run($extra);

        if (isset($this->command)) {
            $time_elapsed_secs = microtime(true) - $start;
            $this->command->getOutput()->writeln("<info>Seeded:</info> $class (" . round($time_elapsed_secs, 2). " seconds)");
        }
    }
}
