<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PriceProviderException extends Exception
{
    public function __construct(
        public readonly string $errorMessage,
        public readonly string $provider,
        public readonly ?string $investmentSymbol = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(self::formatMessage($errorMessage, $provider, $investmentSymbol), 0, $previous);
    }

    /**
     * @return array<string, string>
     */
    public function context(): array
    {
        $context = [
            'provider' => $this->provider,
            'error_message' => $this->errorMessage,
        ];

        if ($this->investmentSymbol !== null) {
            $context['investment_symbol'] = $this->investmentSymbol;
        }

        return $context;
    }

    private static function formatMessage(string $message, string $provider, ?string $investmentSymbol): string
    {
        $details = ["provider: {$provider}"];

        if ($investmentSymbol !== null) {
            $details[] = "symbol: {$investmentSymbol}";
        }

        return sprintf('%s [%s]', $message, implode(', ', $details));
    }
}
