<?php

use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(TransactionTypeSeeder::class, 'fixed');
        $this->call(AccountGroupTableSeeder::class, 'fixed');
        $this->call(CurrencyTableSeeder::class, 'fixed');
        $this->call(CurrencyRateSeeder::class, 'random');
        $this->call(AccountSeeder::class, 'fixed');
        $this->call(CategoryTableSeeder::class, 'fixed');
        $this->call(PayeeSeeder::class, 'fixed');
        $this->call(InvestmentGroupTableSeeder::class, 'fixed');
        $this->call(InvestmentTableSeeder::class, 'fixed');
        $this->call(InvestmentPriceSeeder::class, 'random');
        $this->call(TagTableSeeder::class, 'random');
        $this->call(TransactionSeeder::class, 'random');
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
