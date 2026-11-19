<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Creates the transfer that moves an investment transaction's cash between a foreign-currency account and the account
 * in the user's default currency it settles into.
 *
 * The foreign side of the transfer is the investment transaction's cash flow at exactly the stored precision (4 dp, as
 * MoneyCast stores cashflow_value), so the foreign-currency account is left with no rounding residue. The other side is
 * the amount the user entered: what the broker or bank actually booked in the default currency.
 */
class InvestmentSettlementService
{
    /** The largest value of transaction_details_standard.amount_from/amount_to, DECIMAL(12,4) UNSIGNED */
    public const MAX_TRANSFER_AMOUNT = '99999999.9999';

    public function __construct(
        private TransactionService $transactionService,
    ) {
    }

    /**
     * Must run inside the database transaction that created the investment transaction.
     *
     * @param  string  $settlementAmount  In the settlement account's currency
     *
     * @throws ValidationException
     */
    public function createTransfer(Transaction $investmentTransaction, string $settlementAmount): Transaction
    {
        /** @var TransactionDetailInvestment $config */
        $config = $investmentTransaction->config;

        /** @var AccountEntity $account */
        $account = AccountEntity::query()->with('config')->findOrFail($config->account_id);
        $settlementAccountId = $account->config instanceof Account ? $account->config->settlement_account_id : null;

        if ($settlementAccountId === null) {
            throw ValidationException::withMessages([
                'settlement.amount' => __('The selected account has no settlement account.'),
            ]);
        }

        // The link was valid when it was saved, but the user's default currency can change since. Re-check it here, so an
        // amount entered in the default currency is never recorded against an account in another currency.
        $settlementAccount = AccountEntity::query()->with('config')->find($settlementAccountId);
        $baseCurrencyId = $investmentTransaction->user->baseCurrency()?->id;

        if (
            ! $settlementAccount?->config instanceof Account
            || $baseCurrencyId === null
            || $settlementAccount->config->currency_id !== $baseCurrencyId
            || $account->config->currency_id === $baseCurrencyId
        ) {
            throw ValidationException::withMessages([
                'settlement.amount' => __('The settlement account must be in your default currency.'),
            ]);
        }

        $cashflow = $this->transactionService->getTransactionCashFlow($investmentTransaction);
        $foreignAmount = $cashflow?->getAmount()->toScale(4, RoundingMode::HalfUp);

        if ($foreignAmount === null || $foreignAmount->isZero()) {
            throw ValidationException::withMessages([
                'settlement.amount' => __('This transaction has no cash flow to settle.'),
            ]);
        }

        // Transfer amounts are DECIMAL(12,4) UNSIGNED, while an investment's cash flow can be larger
        if ($foreignAmount->abs()->isGreaterThan(self::MAX_TRANSFER_AMOUNT)) {
            throw ValidationException::withMessages([
                'settlement.amount' => __('The cash flow of this transaction is too large to record as a transfer.'),
            ]);
        }

        $settlement = BigDecimal::of($settlementAmount)->toScale(4, RoundingMode::HalfUp);

        // Cash received in the foreign account moves out to the settlement account; cash spent is funded from it.
        $config = $foreignAmount->isPositive()
            ? [
                'account_from_id' => $account->id,
                'account_to_id' => $settlementAccountId,
                'amount_from' => (string) $foreignAmount,
                'amount_to' => (string) $settlement,
            ]
            : [
                'account_from_id' => $settlementAccountId,
                'account_to_id' => $account->id,
                'amount_from' => (string) $settlement,
                'amount_to' => (string) $foreignAmount->abs(),
            ];

        $transfer = new Transaction([
            'date' => $investmentTransaction->date,
            'transaction_type' => TransactionTypeEnum::TRANSFER,
            'reconciled' => false,
            'schedule' => false,
            'config_type' => 'standard',
        ]);
        $transfer->user_id = $investmentTransaction->user_id;
        $transfer->settles_transaction_id = $investmentTransaction->id;
        $transfer->config()->associate(TransactionDetailStandard::create($config));
        $transfer->save();

        return $transfer;
    }
}
