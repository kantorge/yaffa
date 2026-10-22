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

    /**
     * Statuses a document may be sent back to processing from. A finalized document already
     * created a transaction, so reprocessing it would allow finalizing a duplicate.
     *
     * @return list<self>
     */
    public static function reprocessable(): array
    {
        return [self::ReadyForReview, self::ProcessingFailed];
    }

    public static function isReprocessable(string $status): bool
    {
        return in_array(self::tryFrom($status), self::reprocessable(), true);
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
