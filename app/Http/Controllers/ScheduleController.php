<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use JavaScript;

class ScheduleController extends Controller
{
    private $allAccounts;
    private $allTags;
    private $allCategories;

    public function index()
    {
        // Get all accounts and payees so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', Auth::user()->id)
            ->pluck('name', 'id')
            ->all();

        // Get all tags
        $this->allTags = Auth::user()->tags->pluck('name', 'id')->all();

        // Get all categories
        $this->allCategories = Auth::user()->categories->pluck('full_name', 'id')->all();

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
        ->where(function ($query) {
            return $query->where('schedule', 1)
                ->orWhere('budget', 1);
        })
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
        ->where(function ($query) {
            return $query->where('schedule', 1)
                ->orWhere('budget', 1);
        })
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

                return array_merge($commonData, $baseData);
            })
            ->merge($investmentTransactions
                ->map(function ($transaction) {
                    $commonData = $this->transformDataCommon($transaction);
                    $baseData = $this->transformDataInvestment($transaction);

                    return array_merge($commonData, $baseData);
                }));

        $data = $transactions
            ->sortByDesc('transactionType')
            ->sortBy('start_date')
            ->values();

        JavaScript::put([
            'transactionData' => $data,
        ]);

        return view(
            'schedule.index',
            [
                'transactionData' => $data,
            ]
        );
    }

    private function transformDataCommon(Transaction $transaction)
    {
        return [
            'id' => $transaction->id,
            'transaction_name' => $transaction->transactionType->name,
            'transaction_type' => $transaction->transactionType->type,
            'config_type' => $transaction->config_type,
            'schedule_config' => [
                'start_date' => $transaction->transactionSchedule->start_date->toW3cString(),
                'next_date' => ($transaction->transactionSchedule->next_date ? $transaction->transactionSchedule->next_date->format('Y-m-d') : null),
                'end_date' => ($transaction->transactionSchedule->end_date ? $transaction->transactionSchedule->end_date->format('Y-m-d') : null),
                'frequency' => $transaction->transactionSchedule->frequency,
                'count' => $transaction->transactionSchedule->count,
                'interval' => $transaction->transactionSchedule->interval,
            ],
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'comment' => $transaction->comment,
        ];
    }

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
            'amount_from' => $transaction->config->amount_from,
            'amount_to' => $transaction->config->amount_to,
            'tags' => array_values($itemTags),
            'categories' => array_values($itemCategories),
        ];
    }

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
            'amount_from' => ($amount < 0 ? -$amount : null),
            'amount_to' => ($amount > 0 ? $amount : null),

            'tags' => [],

            'investment_name' => $transaction->config->investment->name,
            'quantity' => $transaction->config->quantity,
            'price' => $transaction->config->price,
        ];
    }
}
