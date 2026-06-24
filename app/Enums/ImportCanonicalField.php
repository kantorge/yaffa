<?php

namespace App\Enums;

enum ImportCanonicalField: string
{
    case Date = 'date';
    case Amount = 'amount';
    case Payee = 'payee';
    case Comment = 'comment';
    case Reference = 'reference';
    case Category = 'category';
    case Ignore = 'ignore';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Fields that permit multiple source columns mapping to the same canonical name.
     *
     * @return list<string>
     */
    public static function multiValueFields(): array
    {
        return [self::Comment->value, self::Reference->value];
    }
}
