<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Exceptions\InvalidAiResponseSchemaException;
use Illuminate\Support\Str;

class AiExtractionSchemaValidator
{
    /**
     * @throws InvalidAiResponseSchemaException
     */
    public function validate(mixed $data): void
    {
        if (! is_array($data) || array_is_list($data)) {
            throw InvalidAiResponseSchemaException::invalidRootType();
        }

        if (! array_key_exists('transaction_type', $data)) {
            throw InvalidAiResponseSchemaException::missingKeys(['transaction_type']);
        }

        $transactionType = $data['transaction_type'];

        if (! is_string($transactionType) || $transactionType === '') {
            throw InvalidAiResponseSchemaException::invalidType('transaction_type', 'non-empty string');
        }

        $transactionType = Str::lower($transactionType);

        if (in_array($transactionType, ['withdrawal', 'deposit', 'transfer'], true)) {
            $this->validateStandardTransactionSchema($data);

            return;
        }

        if (in_array($transactionType, TransactionTypeEnum::investmentTypeValues(), true)) {
            $this->validateInvestmentTransactionSchema($data);

            return;
        }

        throw InvalidAiResponseSchemaException::invalidValue(
            'transaction_type',
            'must be one of withdrawal, deposit, transfer, buy, sell, dividend, interest, add_shares, remove_shares'
        );
    }

    /**
     * @throws InvalidAiResponseSchemaException
     */
    private function validateStandardTransactionSchema(array $data): void
    {
        $requiredKeys = [
            'transaction_type',
            'account',
            'account_from',
            'account_to',
            'payee',
            'date',
            'amount',
            'currency',
            'transaction_items',
        ];

        $this->assertSchemaRequiredAndAllowedKeys($data, $requiredKeys, $requiredKeys);

        $this->assertNullableString($data, 'account');
        $this->assertNullableString($data, 'account_from');
        $this->assertNullableString($data, 'account_to');
        $this->assertNullableString($data, 'payee');
        $this->assertNullableDateString($data, 'date');
        $this->assertNullableNumber($data, 'amount');
        $this->assertNullableString($data, 'currency');

        if (! is_array($data['transaction_items'])) {
            throw InvalidAiResponseSchemaException::invalidType('transaction_items', 'array');
        }

        foreach ($data['transaction_items'] as $index => $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw InvalidAiResponseSchemaException::invalidType("transaction_items.{$index}", 'object');
            }

            $this->assertSchemaRequiredAndAllowedKeys(
                $item,
                ['description', 'amount'],
                ['description', 'amount'],
                "transaction_items.{$index}"
            );

            $this->assertNullableString($item, 'description', "transaction_items.{$index}.description");
            $this->assertNullableNumber($item, 'amount', "transaction_items.{$index}.amount");
        }
    }

    /**
     * @throws InvalidAiResponseSchemaException
     */
    private function validateInvestmentTransactionSchema(array $data): void
    {
        $requiredKeys = [
            'transaction_type',
            'account',
            'investment',
            'date',
            'amount',
            'quantity',
            'price',
            'commission',
            'tax',
            'dividend',
            'currency',
        ];

        $this->assertSchemaRequiredAndAllowedKeys($data, $requiredKeys, $requiredKeys);

        $this->assertNullableString($data, 'account');
        $this->assertNullableString($data, 'investment');
        $this->assertNullableDateString($data, 'date');
        $this->assertNullableNumber($data, 'amount');
        $this->assertNullableNumber($data, 'quantity');
        $this->assertNullableNumber($data, 'price');
        $this->assertNullableNumber($data, 'commission');
        $this->assertNullableNumber($data, 'tax');
        $this->assertNullableNumber($data, 'dividend');
        $this->assertNullableString($data, 'currency');
    }

    /**
     * @throws InvalidAiResponseSchemaException
     */
    private function assertSchemaRequiredAndAllowedKeys(
        array $data,
        array $requiredKeys,
        array $allowedKeys,
        string $context = 'root'
    ): void {
        $missingKeys = array_values(array_diff($requiredKeys, array_keys($data)));
        if (! empty($missingKeys)) {
            throw InvalidAiResponseSchemaException::missingKeys($missingKeys, $context);
        }

        $unexpectedKeys = array_values(array_diff(array_keys($data), $allowedKeys));
        if (! empty($unexpectedKeys)) {
            throw InvalidAiResponseSchemaException::unexpectedKeys($unexpectedKeys, $context);
        }
    }

    /**
     * @throws InvalidAiResponseSchemaException
     */
    private function assertNullableString(array $data, string $key, ?string $path = null): void
    {
        $value = $data[$key];
        if ($value !== null && ! is_string($value)) {
            throw InvalidAiResponseSchemaException::invalidType($path ?? $key, 'string|null');
        }
    }

    /**
     * @throws InvalidAiResponseSchemaException
     */
    private function assertNullableNumber(array $data, string $key, ?string $path = null): void
    {
        $value = $data[$key];
        if ($value !== null && ! is_int($value) && ! is_float($value)) {
            throw InvalidAiResponseSchemaException::invalidType($path ?? $key, 'number|null');
        }
    }

    /**
     * @throws InvalidAiResponseSchemaException
     */
    private function assertNullableDateString(array $data, string $key, ?string $path = null): void
    {
        $value = $data[$key];

        if ($value !== null && ! is_string($value)) {
            throw InvalidAiResponseSchemaException::invalidType($path ?? $key, 'YYYY-MM-DD|null');
        }

        if (is_string($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw InvalidAiResponseSchemaException::invalidValue($path ?? $key, 'must match YYYY-MM-DD');
        }
    }
}
