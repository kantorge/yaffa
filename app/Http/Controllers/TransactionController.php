<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Category;
use App\Tag;
use App\Transaction;
use App\TransactionItem;
use App\TransactionType;
use App\TransactionDetailStandard;
use Illuminate\Support\Facades\DB;
use JavaScript;

use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function create()
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

        return view('transactions.form', ['transaction' => $transaction]);
    }

    public function store(TransactionRequest $request)
    {
        $validated = $request->validated();

        //dd($validated);

        DB::transaction(function () use ($validated) {
            $transaction = Transaction::create($validated);

            $transactionDetails = TransactionDetailStandard::create($validated['config']);
            $transaction->config()->associate($transactionDetails);

            $transactionItems = [];
            foreach ($validated['transactionItems'] as $item) {

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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //get all categories for reference
        $categories = Category::all();
        $categories->sortBy('full_name');

        $transaction = Transaction::with(
            [
                'config',
                'config.accountFrom',
                'config.accountTo',
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

        return view('transactions.form', ['transaction' => $transaction]);
    }
}
