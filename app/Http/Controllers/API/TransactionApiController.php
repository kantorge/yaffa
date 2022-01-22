<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TransactionApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function reconcile(Transaction $transaction, $newState)
    {
        $this->authorize('update', $transaction);

        $transaction->reconciled = $newState;
        $transaction->save();

        return response()->json(
            [
                'success' => true,
            ],
            Response::HTTP_OK
        );
    }

    public function getItem(Transaction $transaction)
    {
        $transaction->load([
            'config',
            'config.accountFrom',
            'config.accountTo',
            'transactionSchedule',
            'transactionType',
            'transactionItems',
            'transactionItems.tags',
            'transactionItems.category',
        ]);


        if ($transaction->transactionType->name === 'withdrawal') {
            $transaction->load([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
            ]);
        }

        if ($transaction->transactionType->name === 'deposit') {
            $transaction->load([
                'config.accountTo.config',
                'config.accountTo.config.currency',
                'config.accountFrom.config',
            ]);
        }

        if ($transaction->transactionType->name === 'transfer') {
            $transaction->load([
                'config.accountFrom.config',
                'config.accountFrom.config.currency',
                'config.accountTo.config',
                'config.accountTo.config.currency',
            ]);
        }

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    public function findTransactions(Request $request)
    {
        if (! $request->hasAny([
            'date_from',
            'date_to',
            'accounts',
            'categories',
            'payees',
            'tags'])) {
            return response()->json(
                [
                    'data' => []
                ],
                Response::HTTP_OK
            );
        }

        $user = Auth::user();

        // Get standard transactions matching any provided criteria
        $standardTransactions = Transaction::where('user_id', $user->id)
            ->where('schedule', 0)
            ->where('budget', 0)
            ->where('config_type', 'transaction_detail_standard')
            ->when($request->has('date_from'), function ($query) use ($request) {
                $query->where('date', '>=', $request->get('date_from'));
            })
            ->when($request->has('date_to'), function ($query) use ($request) {
                $query->where('date', '<=', $request->get('date_to'));
            })
            ->when($request->has('accounts') && $request->get('accounts'), function ($query) use ($request) {
                $query->whereIn('config_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('transaction_details_standard')
                        ->whereIn('account_from_id', $request->get('accounts'))
                        ->orWhereIn('account_to_id', $request->get('accounts'));
                });
            })
            ->when($request->has('payees') && $request->get('payees'), function ($query) use ($request) {
                $query->whereIn('config_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('transaction_details_standard')
                        ->whereIn('account_from_id', $request->get('payees'))
                        ->orWhereIn('account_to_id', $request->get('payees'));
                });
            })
            ->when($request->has('categories') && $request->get('categories'), function ($query) use ($request) {
                $query->whereIn('id', function ($query) use ($request) {
                    $query->select('transaction_id')
                        ->from('transaction_items')
                        ->whereIn('category_id', $request->get('categories'));
                });
            })
            ->when($request->has('tags') && $request->get('tags'), function ($query) use ($request) {
                $query->whereIn('id', function ($query) use ($request) {
                    $query->select('transaction_id')
                        ->from('transaction_items')
                        ->whereIn('id', function ($query) use ($request) {
                            $query->select('transaction_item_id')
                                ->from('transaction_items_tags')
                                ->whereIn('tag_id', $request->get('tags'));
                        });
                });
            })
            ->with([
                'config',
                'transactionType',
                'transactionItems',
                'transactionItems.tags',
            ])
            ->select([
                'id',
                'date',
                'transaction_type_id',
                'comment',
            ])
            ->limit(5)
            ->get();

        return response()->json(
            [
                'data' => $standardTransactions->toArray()
            ],
            Response::HTTP_OK
        );
    }
}
