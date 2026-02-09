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
        return [
            self::WITHDRAWAL,
            self::DEPOSIT,
            self::TRANSFER,
        ];
    }

    /**
     * Get all investment transaction types
     *
     * @return array<TransactionType>
     */
    public static function investmentTypes(): array
    {
        return [
            self::BUY,
            self::SELL,
            self::ADD_SHARES,
            self::REMOVE_SHARES,
            self::DIVIDEND,
            self::INTEREST_YIELD,
        ];
    }

    /**
     * Get all investment types that require an amount
     *
     * @return array<TransactionType>
     */
    public static function investmentTypesWithAmount(): array
    {
        return [
            self::BUY,
            self::SELL,
            self::DIVIDEND,
            self::INTEREST_YIELD,
        ];
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
        $types = [];
        foreach (self::cases() as $case) {
            $types[$case->value] = $case->toArray();
        }

        return $types;
    }

    /**
     * Legacy compatibility: Map from old database ID to enum case
     * This is needed during migration period
     *
     * @deprecated Will be removed after migration is complete
     */
    public static function fromLegacyId(int $id): ?self
    {
        return match ($id) {
            1 => self::WITHDRAWAL,
            2 => self::DEPOSIT,
            3 => self::TRANSFER,
            4 => self::BUY,
            5 => self::SELL,
            6 => self::ADD_SHARES,
            7 => self::REMOVE_SHARES,
            8 => self::DIVIDEND,
            11 => self::INTEREST_YIELD,
            default => null,
        };
    }

    /**
     * Legacy compatibility: Map from old database name to enum case
     *
     * @deprecated Will be removed after migration is complete
     */
    public static function fromLegacyName(string $name): ?self
    {
        return match ($name) {
            'withdrawal' => self::WITHDRAWAL,
            'deposit' => self::DEPOSIT,
            'transfer' => self::TRANSFER,
            'Buy' => self::BUY,
            'Sell' => self::SELL,
            'Add shares' => self::ADD_SHARES,
            'Remove shares' => self::REMOVE_SHARES,
            'Dividend' => self::DIVIDEND,
            'Interest yield' => self::INTEREST_YIELD,
            default => null,
        };
    }
}
