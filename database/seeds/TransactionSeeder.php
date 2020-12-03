<?php

use App\AccountEntity;
use App\Tag;
use App\Transaction;
use App\TransactionDetailStandard;
use App\TransactionDetailInvestment;
use App\TransactionItem;
use App\TransactionSchedule;
use App\TransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
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

    public function seedRandom()
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

    private function seedSql()
    {
        //TODO
    }

    private function seedDb()
    {
        //get old standard transactions and all properties that are 1:1
        $oldStandardTransactions = DB::connection('mysql_fin_migration')
            ->table('transaction_headers')
            ->select(
                'transaction_headers.*',
                'transaction_types.name',
                'transaction_standard_details.payees_id',
                'transaction_standard_details.accounts_from_id',
                'transaction_standard_details.accounts_to_id',
                'transaction_standard_details.amount_to',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
                'payees.name AS payee',
            )
            ->whereNull('deleted_at')
            ->leftJoin('transaction_types', 'transaction_headers.transaction_types_id', '=', 'transaction_types.id')
            ->leftJoin('transaction_schedules', 'transaction_headers.id', '=', 'transaction_schedules.id')
            ->leftJoin('transaction_standard_details', 'transaction_headers.id', '=', 'transaction_standard_details.transaction_headers_id')
            ->leftJoin('payees', 'payees.id', '=', 'transaction_standard_details.payees_id')
            ->where('transaction_types.type', '=', 'standard')
            ->get();


        $this->command->getOutput()->writeln('Standard transactions');

        // creates a new progress bar based on item count
        $progressBar = $this->command->getOutput()->createProgressBar(count($oldStandardTransactions));

        // starts and displays the progress bar
        $progressBar->start();

        //get all old transaction items
        $oldItems = DB::connection('mysql_fin_migration')
            ->table('transaction_items')
            ->get();

        //get all old transaction item tags
        $oldTags = DB::connection('mysql_fin_migration')
            ->table('transaction_items_has_tags')
            ->get();

        foreach ($oldStandardTransactions as $oldTransaction) {
            //create basic transaction, similarly as controller would get it
            $transaction = [
                "id" => $oldTransaction->id,
                "transaction_type_id" => $oldTransaction->transaction_types_id,
                "comment" => $oldTransaction->comment,
                "reconciled" => $oldTransaction->reconciled,
                "schedule" => $oldTransaction->is_schedule,
                "budget" => $oldTransaction->is_budget,
                "config_type" => "transaction_detail_standard",
                "date" => $oldTransaction->date,

                'transactionItems' => [],

                'config' => [],
            ];

            if ($oldTransaction->is_schedule ||$oldTransaction->is_budget) {
                //temporary debug

                if(!property_exists ($oldTransaction, 'start_date')) {
                    $this->command->getOutput()->writeln('');
                    $this->command->getOutput()->writeln('Schedule start date missing');
                    dd($oldTransaction);
                }


                $transaction['schedule_start'] = $oldTransaction->start_date;
                $transaction['schedule_next'] = $oldTransaction->next_date;
                $transaction['schedule_end'] = $oldTransaction->end_date;
                $transaction['schedule_frequency'] = $oldTransaction->frequency;
                $transaction['schedule_interval'] = $oldTransaction->interval;
                $transaction['schedule_count'] = $oldTransaction->count;

            }

            //get items based on transaction id
            $filteredItems = $oldItems->filter(function ($value, $key) use ($oldTransaction, $oldTags) {
                return $value->transaction_headers_id == $oldTransaction->id;
            });

            $filteredItems->each(function($oldItem) use (&$transaction, $oldTransaction, $oldTags) {
                $transaction['transactionItems'][$oldItem->id] = [
                    'category' => $oldItem->categories_id,
                    'amount' => $oldItem->amount,
                    'comment' => $oldItem->comment,
                    'tags' => [],
                ];

                $filteredTags = $oldTags->filter(function ($value, $key) use ($oldItem) {
                    return $value->transaction_items_id == $oldItem->id;
                });


                $filteredTags->each(function($oldTag) use (&$transaction, $oldItem) {
                    $transaction['transactionItems'][$oldItem->id]['tags'][] = $oldTag->tags_id;
                });
            });


            $sum = array_sum(array_column($transaction['transactionItems'], 'amount'));
            switch ($oldTransaction->transaction_types_id) {
                //withdrawal
                case "1":
                    $payee = AccountEntity::where('config_type', 'payee')
                                            ->where('name', $oldTransaction->payee)
                                            ->first();

                    $transaction['config']['account_from_id'] = $oldTransaction->accounts_from_id;
                    $transaction['config']['account_to_id'] = ($payee ? $payee->id : null);//$oldTransaction->payees_id;
                    $transaction['config']['amount_from'] = $sum;
                    $transaction['config']['amount_to'] = $sum;
                    break;
                //deposit
                case "2":
                    $payee = AccountEntity::where('config_type', 'payee')
                                            ->where('name', $oldTransaction->payee)
                                            ->first();

                    $transaction['config']['account_from_id'] = ($payee ? $payee->id : null);//$oldTransaction->payees_id;
                    $transaction['config']['account_to_id'] = $oldTransaction->accounts_to_id;
                    $transaction['config']['amount_from'] = $sum;
                    $transaction['config']['amount_to'] = $sum;
                    break;
                //transaction
                case "3":
                    $transaction['config']['account_from_id'] = $oldTransaction->accounts_from_id;
                    $transaction['config']['account_to_id'] = $oldTransaction->accounts_to_id;
                    $transaction['config']['amount_from'] = $sum;
                    $transaction['config']['amount_to'] = $oldTransaction->amount_to;
                    break;
            }

            //could this be used from the main controller
            $validated = $transaction;

            //dd($validated);

            DB::transaction(function () use ($validated) {
                //temp debugger

                if (!isset($validated['transaction_type_id'])) {
                    $this->command->getOutput()->writeln('');
                    $this->command->getOutput()->writeln('Transaction type missing');

                    dd($validated);
                }


                $transaction = Transaction::create([
                    "transaction_type_id" => $validated['transaction_type_id'],
                    "comment" => $validated['comment'],
                    "reconciled" => $validated['reconciled'],
                    "schedule" => $validated['schedule'],
                    "budget" => $validated['budget'],
                    "config_type" => "transaction_detail_standard",
                    "date" => $validated['date'],
                ]);


                $transactionDetails = TransactionDetailStandard::create($validated['config']);
                $transaction->config()->associate($transactionDetails);

                if (   $transaction->schedule
                   || $transaction->budget) {
                    $transactionSchedule = TransactionSchedule::create(
                            [
                                'transaction_id' => $transaction->id,
                                'start_date' => $validated['schedule_start'],
                                'next_date' => $validated['schedule_next'],
                                'end_date' => $validated['schedule_end'],
                                'frequency' => $validated['schedule_frequency'],
                                'interval' => $validated['schedule_interval'],
                                'count' => $validated['schedule_count'],
                            ]
                    );
                    $transaction->transactionSchedule()->save($transactionSchedule);
                }

                $transactionItems = [];
                foreach ($validated['transactionItems'] as $item) {
                    $newItem = TransactionItem::create(
                        array_merge([
                            'transaction_id' => $transaction->id,
                            'category_id' => $item['category'],
                            'comment' => $item['comment'],
                            'amount' => $item['amount'],
                        ])
                    );

                    if (array_key_exists('tags', $item)) {
                        foreach($item['tags'] as $tag) {
                            $newTag = Tag::find($tag);

                            $newItem->tags()->attach($newTag);
                        }
                    }

                    $transactionItems[]= $newItem;
                }

                $transaction->transactionItems()->saveMany($transactionItems);

                $transaction->push();

            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');

        //investment transactions
        $oldInvestments = DB::connection('mysql_fin_migration')
            ->table('transaction_headers')
            ->select(
                'transaction_headers.*',
                'transaction_types.name',
                'transaction_schedules.start_date',
                'transaction_schedules.next_date',
                'transaction_schedules.end_date',
                'transaction_schedules.frequency',
                'transaction_schedules.interval',
                'transaction_schedules.count',
                'transaction_investment_details.investments_id',
                'transaction_investment_details.accounts_id',
                'transaction_investment_details.price',
                'transaction_investment_details.quantity',
                'transaction_investment_details.commission',
                'transaction_investment_details.dividend')
            ->whereNull('deleted_at')
            ->leftJoin('transaction_types', 'transaction_headers.transaction_types_id', '=', 'transaction_types.id')
            ->leftJoin('transaction_schedules', 'transaction_headers.id', '=', 'transaction_schedules.id')
            ->leftJoin('transaction_investment_details', 'transaction_headers.id', '=', 'transaction_investment_details.transaction_headers_id')
            ->where('transaction_types.type', '=', 'Investment')
            ->get();

        $this->command->getOutput()->writeln('Investment transactions');

        // creates a new progress bar based on item count
        $progressBar = $this->command->getOutput()->createProgressBar(count($oldInvestments));

        // starts and displays the progress bar
        $progressBar->start();

        foreach ($oldInvestments as $item) {
            $transaction = new Transaction([
                "budget" => $item->is_budget,
                "schedule" => $item->is_schedule,
                "comment" => $item->comment,
                "reconciled" => $item->reconciled,
                "date" => $item->date,
                "transaction_type_id" => $item->transaction_types_id,
                //"transaction_type_id" => TransactionType::where('name', $item->name )->first()->id,
                "config_type" => "transaction_detail_standard",
                //"config_id" => $transactionDetails->id
            ]);

            $transactionDetails = TransactionDetailInvestment::create([
                "account_id" => $item->accounts_id,
                "investment_id" => $item->investments_id,
                "price" => $item->price,
                "quantity" => $item->quantity,
                "commission" => $item->commission,
                "dividend" => $item->dividend,
            ]);

            $transaction->config()->associate($transactionDetails);
            $transaction->push();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');

    }

    private function createTransactionSchedule(Transaction $transaction)
    {
        $schedule = factory(TransactionSchedule::class)->create([
            'transaction_id' => $transaction->id,
        ]);
        $transaction->push();
    }

    private function createTransactionProperties(Transaction $transaction)
    {
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
