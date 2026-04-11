<?php

namespace App\Exceptions;

use Exception;
use JsonException;

class InvalidAiResponseSchemaException extends Exception
{
    public static function invalidJson(JsonException $previous): self
    {
        return new self(
            "AI response is not valid JSON: {$previous->getMessage()}",
            0,
            $previous
        );
    }

    public static function invalidRootType(): self
    {
        return new self('AI response must be a JSON object.');
    }

    public static function invalidPayloadStructure(string $message, ?Exception $previous = null): self
    {
        return new self("Invalid AI response payload structure: {$message}", 0, $previous);
    }

    public static function missingKeys(array $keys, string $context = 'root'): self
    {
        $missingKeys = implode(', ', $keys);

        return new self("AI response schema missing required keys in {$context}: {$missingKeys}");
    }

    public static function unexpectedKeys(array $keys, string $context = 'root'): self
    {
        $unexpectedKeys = implode(', ', $keys);

        return new self("AI response schema contains unexpected keys in {$context}: {$unexpectedKeys}");
    }

    public static function invalidValue(string $key, string $rule): self
    {
        return new self("AI response schema invalid value for '{$key}': {$rule}");
    }

    public static function invalidType(string $key, string $expectedType): self
    {
        return new self("AI response schema invalid type for '{$key}', expected {$expectedType}");
    }
}
