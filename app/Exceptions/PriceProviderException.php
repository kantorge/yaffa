<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PriceProviderException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $investmentSymbol = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
