<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class AiProviderFailureException extends Exception
{
    public function __construct(
        private string $step,
        private string $provider,
        private string $model,
        private bool $timeout,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromException(
        Exception $exception,
        string $step,
        string $provider,
        string $model,
        bool $timeout,
    ): self {
        return new self(
            step: $step,
            provider: $provider,
            model: $model,
            timeout: $timeout,
            message: "AI provider error: {$exception->getMessage()}",
            previous: $exception,
        );
    }

    public function step(): string
    {
        return $this->step;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isTimeout(): bool
    {
        return $this->timeout;
    }
}
