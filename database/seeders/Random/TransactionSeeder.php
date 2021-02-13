<?php

namespace Database\Seeders\Random;

use App\Models\AccountEntity;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\TransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run()
    {
        //create standard withdrawals
        $withdrawals = Transaction::factory()
                            ->count(rand(50, 100))
                            ->withdrawal()
                            ->create();

        $withdrawals->each(function($transaction) {
            $this->createTransactionProperties($transaction);
        });

        //create deposits
        $deposits = Transaction::factory()
                            ->count(rand(50, 100))
                            ->deposit()
                            ->create();

        $deposits->each(function($transaction) {
            $this->createTransactionProperties($transaction);
        });

        //create transfers
        $trasfers = Transaction::factory()
                            ->count(rand(20, 50))
                            ->transfer()
                            ->create();

        //create standard withdrawals with schedule
        $withdrawals_with_schedule = Transaction::factory()
                                        ->count(rand(5, 10))
                                        ->withdrawal_schedule()
                                        ->create();

        $withdrawals_with_schedule->each(function($transaction) {
            $this->createTransactionSchedule($transaction);
            $this->createTransactionProperties($transaction);
        });

        //investment buy
        $buys = Transaction::factory()
                    ->count(rand(10, 50))
                    ->buy()
                    ->create();
    }

    private function createTransactionSchedule(Transaction $transaction)
    {
        TransactionSchedule::factory()
            ->create([
                'transaction_id' => $transaction->id,
            ]);
        //$transaction->push();
    }

    private function createTransactionProperties(Transaction $transaction)
    {
        //$newTransactionItems = factory(TransactionItem::class, rand(1, 5))->create([
        $newTransactionItems = TransactionItem::factory()
                                    ->count(rand(1, 5))
                                    /* TODO: this should be used, but new tags are created instead of using existing ones
                                    ->has(
                                        Tag::factory()
                                        ->count(rand(0, 2))
                                    )
                                    */
                                    ->create([
                                        'transaction_id' => $transaction->id,
                                    ]);

        //Grab all tags
        //TODO: can this be called only once per seeding?
        $tags = Tag::all();

        $newTransactionItems->each(function ($item) use ($tags) {

            $item->tags()->attach(
                $tags->random(rand(0, 2))->pluck('id')->toArray()
            );
        });

        //update totals
        $transaction->config->amount_from = $transaction->config->amount_to = $transaction->transactionItems->sum('amount');

        $transaction->transactionItems()->saveMany($newTransactionItems);

        $transaction->push();
    }
}
