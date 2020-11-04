<?php

use App\Tag;
use App\Transaction;
use App\TransactionItem;
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
        $transactions = factory(Transaction::class,rand(5, 10))->create();

        $transactions->each(function ($transaction) {

            $newTransactionItems = factory(TransactionItem::class, rand(1, 5))->create([
                'transaction_id' => $transaction->id,
            ]);

            //Grab all tags
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

        });
    }
}
