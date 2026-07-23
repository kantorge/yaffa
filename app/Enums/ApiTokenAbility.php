<?php

namespace App\Enums;

enum ApiTokenAbility: string
{
    case AccountsRead = 'accounts:read';
    case AccountsWrite = 'accounts:write';
    case TransactionsRead = 'transactions:read';
    case TransactionsWrite = 'transactions:write';
    case InvestmentsRead = 'investments:read';
    case InvestmentsWrite = 'investments:write';
    case CategoriesRead = 'categories:read';
    case CategoriesWrite = 'categories:write';
    case PayeesRead = 'payees:read';
    case PayeesWrite = 'payees:write';
    case TagsRead = 'tags:read';
    case TagsWrite = 'tags:write';
    case ReportsRead = 'reports:read';
    case ImportsWrite = 'imports:write';
    case SettingsWrite = 'settings:write';

    public function label(): string
    {
        return match ($this) {
            self::AccountsRead => __('Accounts: read'),
            self::AccountsWrite => __('Accounts: write'),
            self::TransactionsRead => __('Transactions: read'),
            self::TransactionsWrite => __('Transactions: write'),
            self::InvestmentsRead => __('Investments: read'),
            self::InvestmentsWrite => __('Investments: write'),
            self::CategoriesRead => __('Categories: read'),
            self::CategoriesWrite => __('Categories: write'),
            self::PayeesRead => __('Payees: read'),
            self::PayeesWrite => __('Payees: write'),
            self::TagsRead => __('Tags: read'),
            self::TagsWrite => __('Tags: write'),
            self::ReportsRead => __('Reports: read'),
            self::ImportsWrite => __('Imports: write'),
            self::SettingsWrite => __('Settings: write'),
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
     * @return array<string>
     */
    public static function readOnlyValues(): array
    {
        return array_map(
            fn (self $ability) => $ability->value,
            array_filter(self::cases(), fn (self $ability) => str_ends_with($ability->value, ':read'))
        );
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
