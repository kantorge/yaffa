<?php

namespace Database\Seeders\FinDb;

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
     * Run the database seeds by reading data from legacy system's remote DB
     *
     * @return void
     */
    public function run()
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

            DB::transaction(function () use ($validated) {

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
            $transaction = Transaction::create([
                "budget" => $item->is_budget,
                "schedule" => $item->is_schedule,
                "comment" => $item->comment,
                "reconciled" => $item->reconciled,
                "date" => $item->date,
                "transaction_type_id" => $item->transaction_types_id,
                "config_type" => "transaction_detail_standard",
            ]);

            $transactionDetails = TransactionDetailInvestment::create([
                "account_id" => $item->accounts_id,
                "investment_id" => $item->investments_id,
                "price" => $item->price,
                "quantity" => $item->quantity,
                "commission" => $item->commission,
                "dividend" => $item->dividend,
            ]);

            if (   $item->is_schedule
                || $item->is_budget) {
                $transactionSchedule = TransactionSchedule::create(
                        [
                            'transaction_id' => $transaction->id,
                            'start_date' => $item->start_date,
                            'next_date' => $item->next_date,
                            'end_date' => $item->end_date,
                            'frequency' => $item->frequency,
                            'interval' => $item->interval,
                            'count' => $item->count,
                        ]
                );
                $transaction->transactionSchedule()->save($transactionSchedule);
            }

            $transaction->config()->associate($transactionDetails);
            $transaction->push();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');

    }
}
