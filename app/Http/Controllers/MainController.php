<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Traits\CurrencyTrait;
use App\Http\Traits\ScheduleTrait;
use App\Models\AccountEntity;
use App\Services\TransactionService;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class MainController extends Controller implements HasMiddleware
{
    use CurrencyTrait;
    use ScheduleTrait;

    private $allAccounts;

    private $currentAccount;

    public function __construct(
        private TransactionService $transactionService
    ) {
    }

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
        ];
    }

    public function account_details(Request $request, AccountEntity $account, $withForecast = null)
    {
        /**
         * @get('/account/history/{account}/{withForecast?}')
         * @name('account.history')
         * @middlewares('web', 'auth', 'verified')
         */
        $user = $request->user();

        // Get account details and load to class variable
        $this->currentAccount = $account->load([
            'config',
            'config.currency',
        ]);

        // Get all accounts so their name can be reused
        $this->allAccounts = AccountEntity::where('user_id', $user->id)
            ->pluck('name', 'id')
            ->all();

        // Get and merge transactions
        $transactions = $this->fetchAndEnrichTransactions($user->id);

        // Add forecast instances if needed
        if ($withForecast) {
            $transactions = $this->addForecastInstances($transactions);
        }

        // Prepare display data
        $data = $this->prepareDisplayData($transactions);

        // Prepare schedule data
        $scheduleData = $transactions
            ->filter(fn ($transaction) => $transaction->transactionGroup === 'schedule')
            ->values();

        JavaScriptFacade::put([
            'currency' => $account->config->currency,
            'transactionData' => $data,
            'scheduleData' => $scheduleData,
        ]);

        return view(
            'account.history',
            [
                'account' => $account,
                'withForecast' => $withForecast,
            ]
        );
    }

    /**
     * Fetch and enrich transactions for the account
     */
    private function fetchAndEnrichTransactions(int $userId)
    {
        // Get standard transactions
        $standardTransactions = $this->transactionService->getAccountStandardTransactions(
            $this->currentAccount,
            $userId
        );

        // Get investment transactions
        $investmentTransactions = $this->transactionService->getAccountInvestmentTransactions(
            $this->currentAccount,
            $userId
        );

        // Merge and enrich transactions
        return $standardTransactions
            ->concat($investmentTransactions)
            ->map(function ($transaction) {
                return $this->transactionService->enrichTransactionForDisplay(
                    $transaction,
                    $this->currentAccount,
                    $this->allAccounts
                );
            })
            // Drop scheduled transactions which are not active
            ->filter(fn ($transaction) => !$transaction->schedule || $transaction->transactionSchedule->next_date !== null);
    }

    /**
     * Add forecast instances to transactions
     */
    private function addForecastInstances($transactions)
    {
        return $transactions->concat(
            $this->getScheduleInstances(
                $transactions->filter(fn ($transaction) => $transaction->schedule),
                'next',
            )
        );
    }

    /**
     * Prepare display data with running totals
     */
    private function prepareDisplayData($transactions)
    {
        $subTotal = 0;

        return $transactions
            ->filter(
                fn ($transaction) =>
                $transaction->transactionGroup === 'history'
                || $transaction->transactionGroup === 'forecast'
            )
            ->sortByDesc('transactionType')
            ->sortBy(['date', 'transactionType.amount_multiplier'])
            // Add the opening balance dummy item
            ->prepend($this->currentAccount->config->openingBalance())
            ->map(function ($transaction) use (&$subTotal) {
                $subTotal += $this->calculateTransactionAmount($transaction);
                $transaction->running_total = $subTotal;

                return $transaction;
            })
            ->values();
    }

    /**
     * Calculate the amount to add/subtract for running total
     */
    private function calculateTransactionAmount($transaction): float
    {
        return $transaction->transactionOperator === 1
            ? $transaction->amount_to
            : -1 * $transaction->amount_from;
    }
}
