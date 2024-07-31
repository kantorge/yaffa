<?php

namespace App\Enums;

/**
 * Based on https://developer.gocardless.com/bank-account-data/statuses
 */
enum RequisitionStatus: string
{

    case CREATED = 'CR';
    case GIVING_CONSENT = 'GC';
    case UNDERGOING_AUTHENTICATION = 'UA';
    case REJECTED ='RJ';
    case SELECTING_ACCOUNTS = 'SA';
    case GRANTING_ACCESS = 'GA';
    case LINKED = 'LN';
    case EXPIRED = 'EX';

    public function getDescription(): string
    {
        return match($this) {
            self::CREATED => 'Requisition has been successfully created',
            self::GIVING_CONSENT => 'End-user is giving consent at GoCardless\'s consent screen',
            self::UNDERGOING_AUTHENTICATION => 'End-user is redirected to the financial institution for authentication',
            self::REJECTED => 'Either SSN verification has failed or end-user has entered incorrect credentials',
            self::SELECTING_ACCOUNTS => 'End-user is selecting accounts',
            self::GRANTING_ACCESS => 'End-user is granting access to their account information',
            self::LINKED => 'Account has been successfully linked to requisition',
            self::EXPIRED => 'Access to accounts has expired as set in End User Agreement',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::CREATED => 'Created',
            self::GIVING_CONSENT => 'Giving Consent',
            self::UNDERGOING_AUTHENTICATION => 'Undergoing Authentication',
            self::REJECTED => 'Rejected',
            self::SELECTING_ACCOUNTS => 'Selecting Accounts',
            self::GRANTING_ACCESS => 'Granting Access',
            self::LINKED => 'Linked',
            self::EXPIRED => 'Expired',
        };
    }

    public static function getAllStatuses(): array
    {
        $output = [];
        foreach (self::cases() as $case) {
            $output[] = [
                'id' => $case->value,
                'name' => $case->name,
                'label' => $case->getLabel(),
                'description' => $case->getDescription(),
            ];
        }
        return $output;
    }
}
