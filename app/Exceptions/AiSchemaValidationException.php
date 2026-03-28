<?php

namespace App\Exceptions;

use Exception;

class AiSchemaValidationException extends Exception
{
    public function __construct(
        private string $step,
        string $message,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function step(): string
    {
        return $this->step;
    }
}
