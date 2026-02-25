<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidAiResponseSchemaException;
use App\Services\AiExtractionSchemaValidator;
use PHPUnit\Framework\TestCase;

class AiExtractionSchemaValidatorTest extends TestCase
{
    public function test_accepts_valid_standard_payload(): void
    {
        $validator = new AiExtractionSchemaValidator();

        $payload = [
            'transaction_type' => 'withdrawal',
            'account' => 'Main Account',
            'account_from' => null,
            'account_to' => null,
            'payee' => 'Coffee Shop',
            'date' => '2026-02-25',
            'amount' => 4.5,
            'currency' => 'USD',
            'transaction_items' => [
                [
                    'description' => 'Coffee',
                    'amount' => 4.5,
                ],
            ],
        ];

        $validator->validate($payload);
        $this->assertTrue(true);
    }

    public function test_accepts_valid_investment_payload(): void
    {
        $validator = new AiExtractionSchemaValidator();

        $payload = [
            'transaction_type' => 'buy',
            'account' => 'Brokerage Account',
            'investment' => 'AAPL',
            'date' => '2026-02-25',
            'amount' => null,
            'quantity' => 2.0,
            'price' => 100.25,
            'commission' => 1.5,
            'tax' => 0.2,
            'dividend' => null,
            'currency' => 'USD',
        ];

        $validator->validate($payload);
        $this->assertTrue(true);
    }

    public function test_throws_when_transaction_type_is_missing(): void
    {
        $validator = new AiExtractionSchemaValidator();

        $this->expectException(InvalidAiResponseSchemaException::class);
        $this->expectExceptionMessage('missing required keys in root: transaction_type');

        $validator->validate(['account' => 'Main Account']);
    }

    public function test_throws_when_transaction_type_is_invalid(): void
    {
        $validator = new AiExtractionSchemaValidator();

        $this->expectException(InvalidAiResponseSchemaException::class);
        $this->expectExceptionMessage("invalid value for 'transaction_type'");

        $validator->validate([
            'transaction_type' => 'refund',
            'account' => null,
            'account_from' => null,
            'account_to' => null,
            'payee' => null,
            'date' => null,
            'amount' => null,
            'currency' => null,
            'transaction_items' => [],
        ]);
    }

    public function test_throws_when_standard_payload_contains_unexpected_key(): void
    {
        $validator = new AiExtractionSchemaValidator();

        $this->expectException(InvalidAiResponseSchemaException::class);
        $this->expectExceptionMessage('contains unexpected keys in root: investment');

        $validator->validate([
            'transaction_type' => 'deposit',
            'account' => 'Main Account',
            'account_from' => null,
            'account_to' => null,
            'payee' => 'Employer',
            'date' => '2026-02-25',
            'amount' => 1000,
            'currency' => 'USD',
            'transaction_items' => [],
            'investment' => null,
        ]);
    }

    public function test_throws_when_date_format_is_invalid(): void
    {
        $validator = new AiExtractionSchemaValidator();

        $this->expectException(InvalidAiResponseSchemaException::class);
        $this->expectExceptionMessage("invalid value for 'date': must match YYYY-MM-DD");

        $validator->validate([
            'transaction_type' => 'withdrawal',
            'account' => 'Main Account',
            'account_from' => null,
            'account_to' => null,
            'payee' => 'Coffee Shop',
            'date' => '25-02-2026',
            'amount' => 4.5,
            'currency' => 'USD',
            'transaction_items' => [],
        ]);
    }
}
