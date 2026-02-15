<?php

namespace App\Enums;

enum TransactionType: string
{
    case WITHDRAWAL = 'withdrawal';
    case DEPOSIT = 'deposit';
    case TRANSFER = 'transfer';
    case BUY = 'buy';
    case SELL = 'sell';
    case ADD_SHARES = 'add_shares';
    case REMOVE_SHARES = 'remove_shares';
    case DIVIDEND = 'dividend';
    case INTEREST_YIELD = 'interest_yield';

    /**
     * Get the human-readable label for the transaction type
     */
    public function label(): string
    {
        return match ($this) {
            self::WITHDRAWAL => 'Withdrawal',
            self::DEPOSIT => 'Deposit',
            self::TRANSFER => 'Transfer',
            self::BUY => 'Buy',
            self::SELL => 'Sell',
            self::ADD_SHARES => 'Add shares',
            self::REMOVE_SHARES => 'Remove shares',
            self::DIVIDEND => 'Dividend',
            self::INTEREST_YIELD => 'Interest yield',
        };
    }

    /**
     * Get the amount multiplier for financial calculations
     * -1 = decreases balance, 1 = increases balance, null = no direct effect
     */
    public function amountMultiplier(): ?int
    {
        return match ($this) {
            self::WITHDRAWAL => -1,
            self::DEPOSIT => 1,
            self::TRANSFER => null,
            self::BUY => -1,
            self::SELL => 1,
            self::ADD_SHARES => null,
            self::REMOVE_SHARES => null,
            self::DIVIDEND => 1,
            self::INTEREST_YIELD => 1,
        };
    }

    /**
     * Get the quantity multiplier for investment transactions
     * -1 = decreases shares, 1 = increases shares, null = not applicable
     */
    public function quantityMultiplier(): ?int
    {
        return match ($this) {
            self::WITHDRAWAL => null,
            self::DEPOSIT => null,
            self::TRANSFER => null,
            self::BUY => 1,
            self::SELL => -1,
            self::ADD_SHARES => 1,
            self::REMOVE_SHARES => -1,
            self::DIVIDEND => null,
            self::INTEREST_YIELD => null,
        };
    }

    /**
     * Get the type category (standard or investment)
     */
    public function category(): string
    {
        return match ($this) {
            self::WITHDRAWAL, self::DEPOSIT, self::TRANSFER => 'standard',
            self::BUY, self::SELL, self::ADD_SHARES, self::REMOVE_SHARES, self::DIVIDEND, self::INTEREST_YIELD => 'investment',
        };
    }

    /**
     * Check if this is a standard transaction type
     */
    public function isStandard(): bool
    {
        return $this->category() === 'standard';
    }

    /**
     * Check if this is an investment transaction type
     */
    public function isInvestment(): bool
    {
        return $this->category() === 'investment';
    }

    /**
     * Get all standard transaction types
     *
     * @return array<TransactionType>
     */
    public static function standardTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isStandard());
    }

    /**
     * Get all investment transaction types
     *
     * @return array<TransactionType>
     */
    public static function investmentTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isInvestment());
    }

    /**
     * Get all investment types that require an amount to be recorded with the transaction.
     * This is NOT based on the amount multiplier, but defined as an explicit list.
     *
     * @return array<TransactionType>
     */
    public static function investmentTypesWithAmount(): array
    {
        return [
            self::DIVIDEND,
            self::INTEREST_YIELD,
        ];
    }

    /**
     * Get all investment types that require a quantity.
     * This is based on the quantity multiplier being non-null, which indicates that shares are involved.
     *
     * @return array<TransactionType>
     */
    public static function investmentTypesWithQuantity(): array
    {
        return array_filter(self::investmentTypes(), fn (self $type) => $type->quantityMultiplier() !== null);
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'amount_multiplier' => $this->amountMultiplier(),
            'quantity_multiplier' => $this->quantityMultiplier(),
            'category' => $this->category(),
        ];
    }

    /**
     * Get all transaction types as an array for JSON
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->toArray()])
            ->all();
    }

    /**
     * Helper method to generate the SQL CASE statement for quantity multiplier in queries
     */
    public static function getQuantityMultiplierSqlCase(string $columnName): string
    {
        $cases = array_map(
            fn (self $type) => "WHEN '{$type->value}' THEN {$type->quantityMultiplier()}",
            self::investmentTypesWithQuantity()
        );
        return "CASE {$columnName} " . implode(' ', $cases) . " ELSE 0 END";
    }
}
