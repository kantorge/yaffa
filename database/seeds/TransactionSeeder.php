<?php

use App\Tag;
use App\Transaction;
use App\TransactionItem;
use App\TransactionSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //create standard withdrawals
        $withdrawals = factory(Transaction::class, rand(50, 100))->states('withdrawal')->create();

        $withdrawals->each(function($transaction) {
            $this->createTransactionProperties($transaction);
        });

        //create deposits
        $deposits = factory(Transaction::class, rand(50, 100))->states('deposit')->create();

        $deposits->each(function($transaction) {
            $this->createTransactionProperties($transaction);
        });

        //create transfers
        $transfers = factory(Transaction::class, rand(50, 100))->states('transfer')->create();

        //create standard withdrawals with schedule
        $withdrawals_with_schedule = factory(Transaction::class, rand(1, 5))->states('withdrawal_schedule')->create();

        $withdrawals_with_schedule->each(function($transaction) {
            $this->createTransactionSchedule($transaction);
            $this->createTransactionProperties($transaction);
        });

        //investment buy
        $buys = factory(Transaction::class, rand(10, 50))->states('buy')->create();
    }

    private function createTransactionSchedule(Transaction $transaction)
    {
        $schedule = factory(TransactionSchedule::class)->create([
            'transaction_id' => $transaction->id,
        ]);
        $transaction->push();
    }

    private function createTransactionProperties(Transaction $transaction) {
        $newTransactionItems = factory(TransactionItem::class, rand(1, 5))->create([
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
