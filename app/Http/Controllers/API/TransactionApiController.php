<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TransactionApiController extends Controller
{
    private $allAccounts;
    private $allAccountCurrencies;
    private $allTags;
    private $allCategories;

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
        $transaction->loadStandardDetails();

        return response()->json(
            [
                'transaction' => $transaction,
            ],
            Response::HTTP_OK
        );
    }

    public function getScheduledItems()
    {
        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        // Get all tags
        $this->allTags = Auth::user()->tags->pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = Auth::user()->categories->pluck('full_name', 'id')->all();

        // Get all currencies
        $this->allAccountCurrencies = Auth::user()->accounts()
            ->with([
                'config',
                'config.currency',
            ])
            ->get();

        // Get all standard transactions
        $standardTransactions = Transaction::with(
            [
                'config',
                'transactionType',
                'transactionSchedule',
                'transactionItems',
                'transactionItems.tags',
            ]
        )
        ->where('user_id', Auth::user()->id)
        ->where('schedule', 1)
        ->where(
            'config_type',
            '=',
            'transaction_detail_standard'
        )
        ->get();

        // Get all investment transactions
        $investmentTransactions = Transaction::with(
            [
                'config',
                'config.investment',
                'transactionType',
                'transactionSchedule',
            ]
        )
        ->where('user_id', Auth::user()->id)
        ->where('schedule', 1)
        ->where(
            'config_type',
            '=',
            'transaction_detail_investment'
        )
        ->get();

        // Unify and merge two transaction types
        $transactions = $standardTransactions
            ->map(function ($transaction) {
                $commonData = $this->transformDataCommon($transaction);
                $baseData = $this->transformDataStandard($transaction);
                $currency = $this->getCurrency($transaction);

                return array_merge($commonData, $baseData, $currency);
            })
            ->merge($investmentTransactions
                ->map(function ($transaction) {
                    $commonData = $this->transformDataCommon($transaction);
                    $baseData = $this->transformDataInvestment($transaction);
                    $currency = $this->getCurrency($transaction);

                    return array_merge($commonData, $baseData, $currency);
                }));

        return response()->json(
            [
                'transactions' => $transactions->values(),
            ],
            Response::HTTP_OK
        );
    }

    // TODO: unify with schedule controller
    private function transformDataStandard(Transaction $transaction)
    {
        $transactionArray = $transaction->toArray();

        $itemTags = [];
        $itemCategories = [];
        foreach ($transactionArray['transaction_items'] as $item) {
            if (isset($item['tags'])) {
                foreach ($item['tags'] as $tag) {
                    $itemTags[$tag['id']] = $this->allTags[$tag['id']];
                }
            }
            if (isset($item['category_id'])) {
                $itemCategories[$item['category_id']] = $this->allCategories[$item['category_id']];
            }
        }

        return [
            'transaction_operator' => $transaction->transactionType->amount_operator,
            'account_from_id' => $transaction->config->account_from_id,
            'account_from_name' => $this->allAccounts[$transaction->config->account_from_id] ?? null,
            'account_to_id' => $transaction->config->account_to_id,
            'account_to_name' => $this->allAccounts[$transaction->config->account_to_id] ?? null,
            'amount' => $transaction->config->amount_to,
            'tags' => array_values($itemTags),
            'categories' => array_values($itemCategories),
        ];
    }

    // TODO: unify with schedule controller
    private function transformDataInvestment(Transaction $transaction)
    {
        $amount = $transaction->cashflowValue(null);

        return [
            'transaction_operator' => $transaction->transactionType->amount_operator,
            'quantity_operator' => $transaction->transactionType->quantity_operator,

            'account_from_id' => $transaction->config->account_id,
            'account_from_name' => $this->allAccounts[$transaction->config->account_id],
            'account_to_id' => $transaction->config->investment_id,
            'account_to_name' => $transaction->config->investment->name,
            'amount' => ($amount > 0 ? $amount : 0),

            'tags' => [],

            'investment_name' => $transaction->config->investment->name,
            'quantity' => $transaction->config->quantity,
            'price' => $transaction->config->price,
        ];
    }

    // TODO: unify with schedule controller
    private function transformDataCommon(Transaction $transaction)
    {
        // Prepare schedule related data if schedule is set
        $schedule = null;
        if ($transaction->schedule) {
            $schedule = [
                'start_date' => $transaction->transactionSchedule->start_date->toW3cString(),
                'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->format('Y-m-d') : null),
                'end_date' => ($transaction->transactionSchedule->end_date ? $transaction->transactionSchedule->end_date->format('Y-m-d') : null),
                'frequency' => $transaction->transactionSchedule->frequency,
                'count' => $transaction->transactionSchedule->count,
                'interval' => $transaction->transactionSchedule->interval,
            ];
        }
        return [
            'id' => $transaction->id,
            'date' => $transaction->date,  // Change compared to schedule controller
            'transaction_name' => $transaction->transactionType->name,
            'transaction_type' => $transaction->transactionType->type,
            'config_type' => $transaction->config_type,
            'schedule' => $transaction->schedule,
            'schedule_config' => $schedule,
            'budget' => $transaction->budget,
            'comment' => $transaction->comment,
            'reconciled' => $transaction->reconciled,
        ];
    }

    private function getCurrency(Transaction $transaction)
    {
        $currency = null;

        if ($transaction->transactionType->type === 'Standard') {
            if ($transaction->transactionType->name === 'withdrawal') {
                $currency = $this->allAccountCurrencies->find($transaction->config->account_from_id);
            } elseif ($transaction->transactionType->name === 'deposit') {
                $currency = $this->allAccountCurrencies->find($transaction->config->account_to_id);
            } elseif ($transaction->transactionType->name === 'transfer') {
                $currency = $this->allAccountCurrencies->find($transaction->config->account_to_id);
            }
        } elseif ($transaction->transactionType->type === 'Investment') {
            $currency = $this->allAccountCurrencies->find($transaction->config->account_id);
        }

        return [
            'currency' => $currency->config->currency,
        ];
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
            ->get()
            ->loadMorph('config', [
                TransactionDetailStandard::class => ['config']
            ]);

        // Preprocess data

        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        $this->allAccountCurrencies = Auth::user()->accounts()
            ->with([
                'config',
                'config.currency',
            ])
            ->get();

        // Get all tags
        $this->allTags = Auth::user()->tags->pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = Auth::user()->categories->pluck('full_name', 'id')->all();

        $transactions = $standardTransactions
            ->map(function ($transaction) {
                $commonData = $this->transformDataCommon($transaction);
                $baseData = $this->transformDataStandard($transaction);
                $currency = $this->getCurrency($transaction);

                return array_merge($commonData, $baseData, $currency);
            });

        $data = $transactions
            ->sortByDesc('transactionType')
            ->sortBy('start_date')
            ->values();

        return response()->json(
            [
                'data' => $data
            ],
            Response::HTTP_OK
        );
    }
}
