<?php

namespace App\Enums;

enum AiDocumentSource: string
{
    case ManualUpload = 'manual_upload';
    case ReceivedEmail = 'received_email';
    case GoogleDrive = 'google_drive';

    public function label(): string
    {
        return match ($this) {
            self::ManualUpload => __('Manual upload'),
            self::ReceivedEmail => __('Received email'),
            self::GoogleDrive => __('Google Drive'),
        };
    }

    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
