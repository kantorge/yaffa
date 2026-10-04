<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The plain-text layout every text-based AI document source (forwarded email, mobile notification)
 * is stored in, so they all reach the same processing pipeline in the same shape.
 */
class SourceTextFormatter
{
    public static function format(string $subject, string $from, CarbonInterface $date, string $body): string
    {
        return implode("\n", [
            "Subject: {$subject}",
            "From: {$from}",
            "Date: {$date->format('Y-m-d H:i:s')}",
            '',
            '---',
            '',
            $body,
        ]);
    }
}
