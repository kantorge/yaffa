<?php

namespace App\Services;

/**
 * Value object representing the result of a post-import file disposition attempt.
 */
final class DispositionResult
{
    /**
     * @param bool $success Whether any disposition action succeeded.
     * @param string|null $actionUsed The key of the action that succeeded, or null if all failed.
     * @param array<string, string> $failureReasons Map of action key to failure reason for each failed action.
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $actionUsed,
        public readonly array $failureReasons,
    ) {
    }
}
