<?php

namespace App\Enums;

enum AiDocumentStatus: string
{
    case ReadyForProcessing = 'ready_for_processing';
    case Processing = 'processing';
    case ProcessingFailed = 'processing_failed';
    case ReadyForReview = 'ready_for_review';
    case Finalized = 'finalized';
    case AutoRecorded = 'auto_recorded';
    case Duplicate = 'duplicate';
    case AwaitingItemization = 'awaiting_itemization';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::ReadyForProcessing => __('Ready for processing'),
            self::Processing => __('Processing'),
            self::ProcessingFailed => __('Processing failed'),
            self::ReadyForReview => __('Ready for review'),
            self::Finalized => __('Finalized'),
            self::AutoRecorded => __('Auto-recorded'),
            self::Duplicate => __('Duplicate'),
            self::AwaitingItemization => __('Awaiting itemization'),
            self::Dismissed => __('Dismissed'),
        };
    }

    /**
     * Statuses nothing is waiting on any more. Only these are deleted after the retention period.
     *
     * @return list<self>
     */
    public static function terminal(): array
    {
        return [self::Finalized, self::AutoRecorded, self::Duplicate, self::Dismissed];
    }

    /**
     * Statuses a document is closed against a transaction in. Such a document cannot be reprocessed,
     * because that would let it create a second transaction.
     *
     * @return list<self>
     */
    public static function linkedToTransaction(): array
    {
        return [self::Finalized, self::AutoRecorded, self::Duplicate];
    }

    /**
     * Statuses of a document still waiting for a person (or a receipt), which a manual entry may close.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::ReadyForReview, self::AwaitingItemization];
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }

    public function isLinkedToTransaction(): bool
    {
        return in_array($this, self::linkedToTransaction(), true);
    }

    /**
     * Statuses a document may be sent back to processing from. A document linked to a transaction already
     * created (or matched) one, so reprocessing it would allow recording a duplicate. A dismissed document
     * may be reprocessed, because the user closed it without recording anything.
     *
     * @return list<self>
     */
    public static function reprocessable(): array
    {
        return [self::ReadyForReview, self::ProcessingFailed, self::Dismissed];
    }

    public static function isReprocessable(string $status): bool
    {
        return in_array(self::tryFrom($status), self::reprocessable(), true);
    }

    /**
     * @param  list<self>  $cases
     * @return list<string>
     */
    public static function values(array $cases): array
    {
        return array_map(fn (self $case) => $case->value, $cases);
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
