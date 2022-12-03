<?php

namespace Database\Seeders\Random;

//use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    private $tags;

    /**
     * Run the database seeds by creating random values with factory
     *
     * @return void
     */
    public function run(User $user)
    {
        $this->tags = $user->tags;

        // Create standard withdrawals
        Transaction::factory()
            ->count(rand(10, 20))
            ->withdrawal()
            ->create([
                'user_id' => $user->id,
            ])
            ->each(function ($transaction) {
                $this->createTransactionProperties($transaction);
            });

        // Create deposits
        Transaction::factory()
            ->count(rand(10, 20))
            ->deposit()
            ->create([
                'user_id' => $user->id,
            ])
            ->each(function ($transaction) {
                $this->createTransactionProperties($transaction);
            });

        // Create transfers
        Transaction::factory()
            ->count(rand(5, 10))
            ->transfer()
            ->create([
                'user_id' => $user->id,
            ]);

        // Create standard withdrawals with schedule
        Transaction::factory()
            ->count(rand(5, 10))
            ->withdrawal_schedule()
            ->create([
                'user_id' => $user->id,
                'reconciled' => false,
            ])
            ->each(function ($transaction) {
                $this->createTransactionSchedule($transaction);
                $this->createTransactionProperties($transaction);
            });

        // Investments - buy
        Transaction::factory()
                ->count(rand(5, 10))
                ->buy()
                ->create([
                    'user_id' => $user->id,
                ]);
    }

    private function createTransactionSchedule(Transaction $transaction)
    {
        TransactionSchedule::factory()
            ->create([
                'transaction_id' => $transaction->id,
            ]);
    }

    private function createTransactionProperties(Transaction $transaction)
    {
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

        $newTransactionItems->each(function ($item) {
            $item->tags()->attach(
                $this->tags->random(rand(0, 2))->pluck('id')->toArray()
            );
        });

        //update totals
        $transaction->config->amount_from = $transaction->config->amount_to = $transaction->transactionItems->sum('amount');

        $transaction->transactionItems()->saveMany($newTransactionItems);

        $transaction->push();
    }
}
