<?php

namespace Tests\Unit;

use App\Exceptions\PriceProviderException;
use Tests\TestCase;

class PriceProviderExceptionTest extends TestCase
{
    public function test_message_includes_provider_and_symbol_details(): void
    {
        $exception = new PriceProviderException(
            'Failed to fetch prices from Alpha Vantage',
            'alpha_vantage',
            'AAPL'
        );

        $this->assertSame(
            'Failed to fetch prices from Alpha Vantage [provider: alpha_vantage, symbol: AAPL]',
            $exception->getMessage()
        );
    }

    public function test_context_includes_loggable_exception_details(): void
    {
        $exception = new PriceProviderException(
            'Failed to fetch prices from Alpha Vantage',
            'alpha_vantage',
            'AAPL'
        );

        $this->assertSame([
            'provider' => 'alpha_vantage',
            'error_message' => 'Failed to fetch prices from Alpha Vantage',
            'investment_symbol' => 'AAPL',
        ], $exception->context());
    }
}
