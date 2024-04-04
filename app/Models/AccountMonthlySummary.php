<?php

/**
 * This might not qualify as a model, and should be a simple database operation with the necessary helpers,
 * but it seems to be convenient to to utilize model capabilities for it. We'll see how it goes.
 */

namespace App\Models;

use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Eloquent
 */
class AccountMonthlySummary extends Model
{
    // This model is not using the created_at column, only the updated_at column
    public const null CREATED_AT = null;

    protected $fillable = [
        // First day of the month
        'date',
        'user_id',
        // Reference to the account entity, optionally null for generic budgets
        'account_entity_id',
        // Transaction can be: 'account_balance', 'investment_value'
        'transaction_type',
        // Data type can be: 'fact', 'forecast', 'budget'
        'data_type',
        // The monthly change for standard transactions, and month end value for investments
        // Always in the currency of the account, or the base currency for generic budgets
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountEntity(): BelongsTo
    {
        return $this->belongsTo(AccountEntity::class);
    }

    /**
     * Helper function to recalculate the summary fact for a given month,
     * for standard transactions, for a specified account.
     */
    public static function calculateAccountBalanceFact(AccountEntity $accountEntity, Carbon $month)
    {
        // New variables cloned from the date as start and end of the month
        $startOfMonth = $month->clone()->startOfMonth();
        $endOfMonth = $month->clone()->endOfMonth();

        // Get the sum of all the standard transactions for the given month for this account
        $valueFrom = DB::table('transactions')
            ->join(
                'transaction_details_standard',
                'transactions.config_id',
                '=',
                'transaction_details_standard.id'
            )
            ->where('config_type', 'standard')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('schedule', 0)
            ->where('budget', 0)
            ->where('transaction_details_standard.account_from_id', $accountEntity->id)
            ->sum('transaction_details_standard.amount_from');

        $valueTo = DB::table('transactions')
            ->join(
                'transaction_details_standard',
                'transactions.config_id',
                '=',
                'transaction_details_standard.id'
            )
            ->where('config_type', 'standard')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('schedule', 0)
            ->where('budget', 0)
            ->where('transaction_details_standard.account_to_id', $accountEntity->id)
            ->sum('transaction_details_standard.amount_to');

        // Get the cash flow value for all investment transactions for the given month for this account
        $valueInvestment = DB::table('transactions')
            ->join(
                'transaction_details_investment',
                'transactions.config_id',
                '=',
                'transaction_details_investment.id'
            )
            ->where('config_type', 'investment')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('schedule', 0)
            ->where('budget', 0)
            ->where('transaction_details_investment.account_id', $accountEntity->id)
            ->sum('cashflow_value');

        return $valueInvestment + $valueTo - $valueFrom;
    }

    /**
     * This is a helper function to calculate the value of investments owned at the end of a given month,
     * for a given account. Under the hood, we need to get the quantity up to this date, and the latest
     * known price up to this date.
     */
    public static function calculateInvestmentValueFact(AccountEntity $accountEntity, Carbon $date)
    {
        // New variable cloned from the date as the end of the target month
        $endOfMonth = $date->clone()->endOfMonth();

        /** @var Account $account $investments */
        $account = $accountEntity->config;

        // Get the associated quantity of all the investment transactions for the given month for this account
        $investments = $account->getAssociatedInvestmentsAndQuantity($endOfMonth);

        // All associated investments should be in the same currency as the account, so no conversion is needed
        // Get the current value of all the investments
        return $investments->sum(
            function ($item) use ($endOfMonth) {
                if ($item->quantity === 0 || $item->quantity === 0.0) {
                    return 0;
                }

                $investment = Investment::find($item->investment_id);

                return $item->quantity * $investment->getLatestPrice('combined', $endOfMonth);
            }
        );
    }
}
