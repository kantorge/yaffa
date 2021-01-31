<?php

namespace Database\Seeders\FinDb;

use App\Models\AccountEntity;
use App\Models\Payee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
    {
        $old = DB::connection('mysql_fin_migration')->table('payees')->get();

        // creates a new progress bar based on item count
        $progressBar = $this->command->getOutput()->createProgressBar(count($old));

        // starts and displays the progress bar
        $progressBar->start();

        foreach ($old as $item) {
            $account = new AccountEntity(
                [
                    'name' => $item->name,
                    'active' => $item->active,
                    'config_type' => 'payee',
                ]
            );

            $payeeConfig = new Payee(
                [
                    'category_id' => $item->categories_id,
                ]
            );
            $payeeConfig->save();

            $account->config()->associate($payeeConfig);

            $account->save();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');
    }
}
