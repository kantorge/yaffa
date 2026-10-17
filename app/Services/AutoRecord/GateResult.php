<?php

namespace App\Services\AutoRecord;

use App\Models\AccountEntity;
use App\Models\PayeeProfile;
use App\Models\TransactionTemplate;

/**
 * Outcome of the auto-record gates for one document. `hold` is a pass-through "not yet": every gate before
 * completeness passed, but a receipt is still missing. When the gates pass, the resolved payee, account,
 * profile and (optional) template are carried along for the recorder.
 */
final readonly class GateResult
{
    /**
     * @param  list<string>  $reasons  Why the gates passed (or the single reason one failed)
     * @param  array<string, mixed>  $draft  The normalized draft the gates were evaluated on
     * @param  list<array{amount: string, category_id: int, comment?: string|null, tags?: list<int>}>  $items  What would be recorded
     */
    public function __construct(
        public bool $passed,
        public ?string $failedGate = null,
        public array $reasons = [],
        public bool $hold = false,
        public array $draft = [],
        public ?AccountEntity $payee = null,
        public ?int $accountId = null,
        public ?PayeeProfile $profile = null,
        public ?TransactionTemplate $template = null,
        public array $items = [],
    ) {
    }

    public static function fail(string $gate, string $reason, bool $hold = false): self
    {
        return new self(false, $gate, [$reason], $hold);
    }

    public function reason(): string
    {
        return implode(' ', $this->reasons);
    }
}
