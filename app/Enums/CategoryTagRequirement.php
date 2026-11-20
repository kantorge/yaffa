<?php

namespace App\Enums;

/**
 * Whether items in a category need a tag (e.g. a claim number for recharges).
 * Never enforced on save: missing tags are only prompted for and listed on a report.
 */
enum CategoryTagRequirement: string
{
    case NONE = 'none';
    case REQUIRED = 'required';
    case REQUIRED_NIL = 'required_nil';

    public function requiresTag(): bool
    {
        return $this !== self::NONE;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
