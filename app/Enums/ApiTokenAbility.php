<?php

namespace App\Enums;

enum ApiTokenAbility: string
{
    case Read = 'read';
    case Write = 'write';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Read => __('Read'),
            self::Write => __('Write'),
            self::Settings => __('Account & security settings'),
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $ability) => $ability->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
