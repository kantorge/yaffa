<?php

namespace App\Enums;

enum CheckpointType: string
{
    case CASH = 'cash';
    case INVESTMENT = 'investment';
    case TOTAL = 'total';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
