<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class AiSchemaValidationException extends Exception
{
    public function __construct(
        private string $step,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function step(): string
    {
        return $this->step;
    }
}
