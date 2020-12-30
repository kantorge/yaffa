<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Category;
use App\Investment;
use App\Tag;
use App\Transaction;
use App\TransactionItem;
use App\TransactionType;
use App\TransactionDetailStandard;
use App\TransactionSchedule;
use Illuminate\Support\Facades\DB;
use JavaScript;

use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function createStandard()
    {
        //set action for future usage
        $action = 'create';

        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $baseTransactionData = [
            'transactionType' => 'withdrawal',
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        $transaction = new Transaction([
            'transaction_type_id' => TransactionType::where('name', 'withdrawal')->first()->id
        ]);


        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    public function createInvestment()
    {
        $transaction = new Transaction([
            'transaction_type_id' => TransactionType::where('name', 'buy')->first()->id
        ]);

        return view('transactions.form_investment', ['transaction' => $transaction]);
    }

    public function storeStandard (TransactionRequest $request)
    {
        $validated = $request->validated();

        //dd($validated);

        DB::transaction(function () use ($validated) {
            $transaction = Transaction::create($validated);

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
                if(is_null($item['amount'])) {
                    continue;
                }

                $newItem = TransactionItem::create(
                    array_merge(
                        $item,
                        ['transaction_id' => $transaction->id]
                    )
                );

                if (array_key_exists('tags', $item)) {
                    foreach($item['tags'] as $tag) {
                        $newTag = Tag::firstOrCreate(
                            ['id' => $tag],
                            ['name' => $tag]
                        );

                        $newItem->tags()->attach($newTag);
                    }
                }

                $transactionItems[]= $newItem;
            }

            $transaction->transactionItems()->saveMany($transactionItems);

            $transaction->push();

        });

        add_notification('Transaction added', 'success');

        return redirect("/");
    }

    public function storeInvestment(TransactionRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $transaction = Transaction::create($validated);

            $transactionDetails = TransactionDetailInvestment::create($validated['config']);
            $transaction->config()->associate($transactionDetails);

            if (   $transaction->schedule) {
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

            $transaction->push();

        });

        add_notification('Transaction added', 'success');

        return redirect("/");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editStandard($id)
    {
        //set action for future usage
        $action = 'edit';

        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $transaction = Transaction::with(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionSchedule',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ])
            ->find($id);

            //dd($transaction);

        $baseTransactionData = [
            'transactionType' => $transaction->transactionType->name,
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    public function editInvestment(Transaction $transaction)
    {
        $transaction->load(
            [
                'config',
                'config.account',
                'config.investment',
                'transactionSchedule',
                'transactionType',
            ]
        );

        return view('transactions.form_investment', ['transaction' => $transaction]);
    }

    public function updateStandard (TransactionRequest $request, Transaction $transaction)
    {
        $validated = $request->validated();

        $transaction->fill($validated);
        $transaction->config->fill($validated['config']);

        if (   $transaction->schedule
            || $transaction->budget) {

                $transaction->transactionSchedule()->start_date = $validated['schedule_start'];
                $transaction->transactionSchedule()->next_date = $validated['schedule_next'];
                $transaction->transactionSchedule()->end_date = $validated['schedule_end'];
                $transaction->transactionSchedule()->frequency = $validated['frequency'];
                $transaction->transactionSchedule()->interval = $validated['interval'];
                $transaction->transactionSchedule()->count = $validated['count'];
        }

        $transactionItems = [];
        foreach ($validated['transactionItems'] as $item) {
            if(is_null($item['amount'])) {
                continue;
            }

            $newItem = TransactionItem::create(
                array_merge(
                    $item,
                    ['transaction_id' => $transaction->id]
                )
            );

            if (array_key_exists('tags', $item)) {
                foreach($item['tags'] as $tag) {
                    $newTag = Tag::firstOrCreate(
                        ['id' => $tag],
                        ['name' => $tag]
                    );

                    $newItem->tags()->attach($newTag);
                }
            }

            $transactionItems[]= $newItem;
        }

        $transaction->transactionItems()->delete();
        $transaction->transactionItems()->saveMany($transactionItems);

        $transaction->push();

        add_notification('Transaction updated', 'success');

        return redirect("/");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return redirect()->back();
    }

    public function skipScheduleInstance(Transaction $transaction)
    {
        $transaction->transactionSchedule->skipNextInstance();
        add_notification('Transaction schedule instance skipped', 'success');
        return redirect()->back();
    }

    /**
     * Show the form for cloning selected resource. (Load model, remove ID)
     *
     * @param  Transaction $transaction
     * @return \Illuminate\Http\Response
     */
    public function cloneStandard(Transaction $transaction)
    {
        //set action for future usage
        $action = 'clone';

        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $transaction->load(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionSchedule',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ]);

        //remove Id, so item is considered a new transaction
        $transaction->id = null;

        $baseTransactionData = [
            'transactionType' => $transaction->transactionType->name,
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }

    public function enterWithEditStandard(Transaction $transaction)
    {
        //set action for future usage
        $action = 'enter';

        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $transaction->load(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
                'transactionSchedule',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
                'transactionItems.category',
            ]);

        //remove Id, so item is considered a new transaction
        $transaction->id = null;

        //reset schedule and budget flags
        $transaction->schedule = 0;
        $transaction->budget = 0;

        //date is next date
        $transaction->date = $transaction->transactionSchedule->next_date;

        $baseTransactionData = [
            'transactionType' => $transaction->transactionType->name,
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', [
            'transaction' => $transaction,
            'action' => $action,
        ]);
    }
}
