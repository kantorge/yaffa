<?php

namespace App\Exceptions;

use Exception;

class AiResponseParseException extends Exception
{
    public function __construct(
        private string $step,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function step(): string
    {
        return $this->step;
    }
}
