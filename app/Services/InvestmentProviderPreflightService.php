<?php

namespace App\Services;

use App\Exceptions\PriceProviderException;
use App\Models\Investment;

class InvestmentProviderPreflightService
{
    public function __construct(private InvestmentPriceProviderContextResolver $contextResolver)
    {
    }

    /**
     * @return array{ok: bool, reason: string|null, context: array<string, mixed>|null}
     */
    public function validate(Investment $investment): array
    {
        try {
            $context = $this->contextResolver->resolve($investment);

            return [
                'ok' => true,
                'reason' => null,
                'context' => $context,
            ];
        } catch (PriceProviderException $exception) {
            return [
                'ok' => false,
                'reason' => $exception->errorMessage,
                'context' => null,
            ];
        }
    }
}
