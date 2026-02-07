<?php

namespace App\Enums;

enum AiDocumentStatus: string
{
    case ReadyForProcessing = 'ready_for_processing';
    case Processing = 'processing';
    case ProcessingFailed = 'processing_failed';
    case ReadyForReview = 'ready_for_review';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::ReadyForProcessing => __('Ready for processing'),
            self::Processing => __('Processing'),
            self::ProcessingFailed => __('Processing failed'),
            self::ReadyForReview => __('Ready for review'),
            self::Finalized => __('Finalized'),
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
