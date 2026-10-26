<?php

namespace App\Services;

use App\Enums\SameEventOutcome;
use App\Models\AiDocument;
use App\Models\Transaction;

final readonly class SameEventResult
{
    public function __construct(
        public SameEventOutcome $outcome,
        public ?Transaction $transaction = null,
        public ?AiDocument $document = null,
    ) {
    }
}
