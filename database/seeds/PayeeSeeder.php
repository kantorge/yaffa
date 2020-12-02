<?php

use App\AccountEntity;
use App\Category;
use App\Payee;
use Illuminate\Database\Seeder;

class PayeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($extra)
    {
        switch($extra) {
            case 'random':
                $this->seedRandom();
                break;
            case 'fixed':
                $this->seedFixed();
                break;
            case 'sql':
                $this->seedSql();
                break;
            case 'db':
                $this->seedDb();
                break;
        }
    }

    private function seedRandom() {
        //TODO
    }

    private function seedFixed() {
        /* specific values */
        $payee = new AccountEntity(
            [
                'name' => 'Auchan',
                'active' => 1,
                'config_type' => 'payee',
            ]
        );

        $payeeConfig = new Payee(
            [
                'category_id' => Category::where('name', 'Alapanyag, fűszer, konzerv')->pluck('id')->first(),
            ]
        );
        $payeeConfig->save();

        $payee->config()->associate($payeeConfig);

        $payee->save();

        /* specific values */
        $payee = new AccountEntity(
            [
                'name' => 'CBA',
                'active' => 1,
                'config_type' => 'payee',
            ]
        );

        $payeeConfig = new Payee();
        $payeeConfig->save();

        $payee->config()->associate($payeeConfig);

        $payee->save();
    }

    private function seedSql() {
        //TODO
    }

    private function seedDb()
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
