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

        return view('transactions.form_standard', ['transaction' => $transaction]);
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
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editStandard($id)
    {
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
            //dd($transaction->toArray());

        $baseTransactionData = [
            'transactionType' => $transaction->transactionType->name,
            'assets' => [
                'categories' => $categories->keyBy('id')->pluck('full_name','id')->toArray(),
            ]
        ];

        JavaScript::put(['baseTransactionData' => $baseTransactionData]);

        return view('transactions.form_standard', ['transaction' => $transaction]);
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
}
