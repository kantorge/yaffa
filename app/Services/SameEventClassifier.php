<?php

namespace App\Services;

use App\Enums\AiDocumentStatus;
use App\Enums\SameEventOutcome;
use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionOrigin;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Decides whether a document describes a purchase that is already known: recorded as a transaction, or
 * described by another open document. A key match (exact amount, date window, same payee, same account when
 * both sides have one) only makes two items candidates; whether they are the same purchase or a repeat is
 * decided by the same-event signals. See the fast transaction entry concept, "Duplicate detection".
 *
 * Only withdrawals, deposits and transfers are classified. Investments have no payee and are left to the
 * score based DuplicateDetectionService.
 *
 * @phpstan-type Key array{type: string, date: Carbon, amount: BigDecimal, from: ?int, to: ?int}
 */
class SameEventClassifier
{
    private const array CLASSIFIED_TYPES = ['withdrawal', 'deposit', 'transfer'];

    private const array HASH_MATCH_STATUSES = [
        AiDocumentStatus::ReadyForReview,
        AiDocumentStatus::AwaitingItemization,
        AiDocumentStatus::Finalized,
        AiDocumentStatus::AutoRecorded,
    ];

    public function __construct(
        private readonly TransactionDraftService $draftService,
        private readonly AiUserSettingsResolver $settingsResolver,
    ) {
    }

    /**
     * Classify a processed document against the user's transactions and other open documents.
     */
    public function classify(AiDocument $document): SameEventResult
    {
        $user = $document->user;

        if ($document->content_hash !== null) {
            $repeat = AiDocument::query()
                ->where('user_id', $user->id)
                ->where('content_hash', $document->content_hash)
                ->whereKeyNot($document->id)
                ->whereIn('status', AiDocumentStatus::values(self::HASH_MATCH_STATUSES))
                ->orderBy('id')
                ->first();

            if ($repeat) {
                return new SameEventResult(SameEventOutcome::ExactRepeat, document: $repeat);
            }
        }

        $key = $this->keyFromDocument($document);

        if ($key === null) {
            return new SameEventResult(SameEventOutcome::None);
        }

        $settings = $this->settingsResolver->resolveForUser($user);
        $windowMinutes = (int) $settings['same_event_minutes'];
        $incoming = $this->signals($document);

        $candidate = false;
        $nearTransaction = null;

        foreach ($this->transactionMatches($user, $key, null, true) as $match) {
            if (! $match['exact']) {
                $nearTransaction ??= $match['transaction'];

                continue;
            }

            $verdict = $this->transactionVerdict($incoming, $match['linked'], $windowMinutes);

            if ($verdict === 'same') {
                return new SameEventResult(SameEventOutcome::SameEventTransaction, transaction: $match['transaction']);
            }

            $candidate = $candidate || $verdict === 'candidate';
        }

        $candidateDocument = null;

        foreach ($this->openDocumentMatches($user, $key, $document->id) as $other) {
            $verdict = $this->pairVerdict($incoming, $this->signals($other), false, $windowMinutes);

            if ($verdict === 'same') {
                return new SameEventResult(SameEventOutcome::SameEventDocument, document: $other);
            }

            if ($verdict === 'candidate') {
                $candidate = true;
                $candidateDocument ??= $other;
            }
        }

        if ($candidate) {
            return new SameEventResult(SameEventOutcome::Candidate, document: $candidateDocument);
        }

        if ($nearTransaction) {
            return new SameEventResult(SameEventOutcome::NearMatch, transaction: $nearTransaction);
        }

        return new SameEventResult(SameEventOutcome::None);
    }

    /**
     * The matching key of a manual entry, or null when it cannot be matched (transfers and investments,
     * a missing payee or account, a non-positive amount).
     *
     * @return Key|null
     */
    public function keyFromEntry(string $transactionType, ?int $accountId, ?int $payeeId, string $date, string $amount): ?array
    {
        $sides = match ($transactionType) {
            'withdrawal' => ['from' => $accountId, 'to' => $payeeId],
            'deposit' => ['from' => $payeeId, 'to' => $accountId],
            default => null,
        };

        return $sides === null ? null : $this->makeKey($transactionType, $date, $amount, $sides['from'], $sides['to']);
    }

    /**
     * Committed transactions matching the key, each with the documents linked to it. A near match has the
     * same payee, accounts and date window, but an amount within the configured tolerance instead of equal.
     *
     * @param  Key  $key
     * @return Collection<int, array{transaction: Transaction, exact: bool, linked: array<int, AiDocument>}>
     */
    public function transactionMatches(User $user, array $key, ?int $excludeTransactionId = null, bool $includeNear = false): Collection
    {
        $settings = $this->settingsResolver->resolveForUser($user);
        $windowDays = max(1, (int) $settings['duplicate_date_window_days']);
        $tolerance = BigDecimal::of((string) $settings['duplicate_amount_tolerance_percent']);

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->where('config_type', 'standard')
            ->where('transaction_type', $key['type'])
            ->whereBetween('date', [
                $key['date']->copy()->subDays($windowDays)->toDateString(),
                $key['date']->copy()->addDays($windowDays)->toDateString(),
            ])
            ->when($excludeTransactionId, fn ($query) => $query->whereKeyNot($excludeTransactionId))
            ->with('config')
            ->get();

        $matches = collect();

        foreach ($transactions as $transaction) {
            $config = $transaction->config;

            if (! $config instanceof TransactionDetailStandard
                || ! $this->sidesMatch($key, $config->account_from_id, $config->account_to_id)) {
                continue;
            }

            $amount = $config->amount_from->getAmount();
            $exact = $amount->isEqualTo($key['amount']);

            $near = $includeNear
                && ! $exact
                && $amount->isGreaterThan(0)
                && $amount->minus($key['amount'])->abs()->multipliedBy(100)->isLessThanOrEqualTo($amount->multipliedBy($tolerance));

            if ($exact || $near) {
                $matches->push(['transaction' => $transaction, 'exact' => $exact, 'linked' => []]);
            }
        }

        return $this->attachLinkedDocuments($matches);
    }

    /**
     * The user's open documents (waiting for review or for a receipt) matching the key exactly.
     *
     * @param  Key  $key
     * @return Collection<int, AiDocument>
     */
    public function openDocumentMatches(User $user, array $key, ?int $excludeDocumentId = null): Collection
    {
        $windowDays = max(1, (int) $this->settingsResolver->resolveForUser($user)['duplicate_date_window_days']);

        return AiDocument::query()
            ->with(['receivedMail', 'aiDocumentFiles'])
            ->where('user_id', $user->id)
            ->whereIn('status', AiDocumentStatus::values(AiDocumentStatus::open()))
            ->when($excludeDocumentId, fn ($query) => $query->whereKeyNot($excludeDocumentId))
            ->get()
            ->filter(function (AiDocument $other) use ($key, $windowDays): bool {
                $otherKey = $this->keyFromDocument($other);

                return $otherKey !== null
                    && $otherKey['type'] === $key['type']
                    && $otherKey['amount']->isEqualTo($key['amount'])
                    && abs($otherKey['date']->diffInDays($key['date'])) <= $windowDays
                    && $this->sidesMatch($key, $otherKey['from'], $otherKey['to']);
            })
            ->values();
    }

    /**
     * The matching key of a processed document's draft, or null when the draft cannot be matched.
     *
     * @return Key|null
     */
    public function keyFromDocument(AiDocument $document): ?array
    {
        if (! is_array($document->processed_transaction_data)) {
            return null;
        }

        try {
            $draft = $this->draftService->normalize($document->processed_transaction_data, $document->user)['draft'];
        } catch (ValidationException) {
            return null;
        }

        $type = $draft['transaction_type'] ?? null;
        $config = $draft['config'] ?? [];

        if (($draft['config_type'] ?? null) !== 'standard' || ! in_array($type, self::CLASSIFIED_TYPES, true)) {
            return null;
        }

        return $this->makeKey(
            $type,
            (string) ($draft['date'] ?? ''),
            (string) ($config['amount_from'] ?? ''),
            $config['account_from_id'] ?? null,
            $config['account_to_id'] ?? null,
        );
    }

    /**
     * @return Key|null
     */
    private function makeKey(string $type, string $date, string $amount, ?int $from, ?int $to): ?array
    {
        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date);
            $parsedAmount = BigDecimal::of($amount);
        } catch (Throwable) {
            // An unparseable date or amount (Carbon and brick/math both throw) cannot be matched
            return null;
        }

        if (! $parsedDate || ! $parsedAmount->isGreaterThan(0)) {
            return null;
        }

        return ['type' => $type, 'date' => $parsedDate->startOfDay(), 'amount' => $parsedAmount, 'from' => $from, 'to' => $to];
    }

    /**
     * The payee side must be known and equal. The account side (and both sides of a transfer) must be equal
     * wherever both the key and the candidate have one.
     *
     * @param  Key  $key
     */
    private function sidesMatch(array $key, ?int $from, ?int $to): bool
    {
        $same = fn (?int $a, ?int $b): bool => $a === null || $b === null || $a === $b;

        return match ($key['type']) {
            'withdrawal' => $key['to'] !== null && $key['to'] === $to && $same($key['from'], $from),
            'deposit' => $key['from'] !== null && $key['from'] === $from && $same($key['to'], $to),
            default => $same($key['from'], $from) && $same($key['to'], $to)
                && ($key['from'] !== null || $key['to'] !== null),
        };
    }

    /**
     * Load the documents linked to the transactions: through `ai_document_id` (created from, or finalized)
     * and through `duplicate_of` origins (closed against it).
     *
     * @param  Collection<int, array{transaction: Transaction, exact: bool, linked: array<int, AiDocument>}>  $matches
     * @return Collection<int, array{transaction: Transaction, exact: bool, linked: array<int, AiDocument>}>
     */
    private function attachLinkedDocuments(Collection $matches): Collection
    {
        if ($matches->isEmpty()) {
            return $matches;
        }

        $transactionIds = $matches->map(fn (array $match) => $match['transaction']->id);

        $originDocuments = TransactionOrigin::query()
            ->whereIn('transaction_id', $transactionIds)
            ->where('relation', TransactionOrigin::RELATION_DUPLICATE_OF)
            ->where('origin_type', 'ai_document')
            ->whereNotNull('origin_id')
            ->get(['transaction_id', 'origin_id']);

        $documents = AiDocument::query()
            ->whereIn('id', $originDocuments->pluck('origin_id')
                ->merge($matches->pluck('transaction.ai_document_id'))
                ->filter()
                ->unique())
            ->get()
            ->keyBy('id');

        return $matches->map(function (array $match) use ($originDocuments, $documents): array {
            $transaction = $match['transaction'];
            $ids = $originDocuments->where('transaction_id', $transaction->id)->pluck('origin_id')
                ->push($transaction->ai_document_id)
                ->filter()
                ->unique();

            $match['linked'] = $ids->map(fn ($id) => $documents->get($id))->filter()->values()->all();

            return $match;
        });
    }

    /**
     * @param  array{kind: string, ref: ?string, hash: ?string, moment: ?Carbon}  $incoming
     * @param  array<int, AiDocument>  $linked
     * @return 'same'|'candidate'|'separate'
     */
    private function transactionVerdict(array $incoming, array $linked, int $windowMinutes): string
    {
        // A transaction nobody has documented yet (entered by hand) is complementary to any document
        if ($linked === []) {
            return 'same';
        }

        $verdicts = array_map(
            fn (AiDocument $document) => $this->pairVerdict($incoming, $this->signals($document), true, $windowMinutes),
            $linked
        );

        // One to one: a single document of the same kind linked to the transaction is enough to block it
        return match (true) {
            in_array('separate', $verdicts, true) => 'separate',
            in_array('candidate', $verdicts, true) => 'candidate',
            default => 'same',
        };
    }

    /**
     * @param  array{kind: string, ref: ?string, hash: ?string, moment: ?Carbon}  $a
     * @param  array{kind: string, ref: ?string, hash: ?string, moment: ?Carbon}  $b
     * @param  bool  $oneToOne  $b is already linked to a transaction, which absorbs at most one document of each kind
     * @return 'same'|'candidate'|'separate'
     */
    private function pairVerdict(array $a, array $b, bool $oneToOne, int $windowMinutes): string
    {
        $sameKind = $a['kind'] === $b['kind'];
        $complementary = ($a['kind'] === 'bank_notification' && in_array($b['kind'], ['receipt', 'invoice'], true))
            || ($b['kind'] === 'bank_notification' && in_array($a['kind'], ['receipt', 'invoice'], true));
        $bothTimed = $a['moment'] !== null && $b['moment'] !== null;

        $signal = ($a['hash'] !== null && $a['hash'] === $b['hash'])
            || ($a['ref'] !== null && $a['ref'] === $b['ref'])
            || ($bothTimed && abs($a['moment']->diffInMinutes($b['moment'])) <= $windowMinutes);

        return match (true) {
            // A transaction that already has a document of this kind cannot absorb another one
            $signal => $oneToOne && $sameKind ? 'candidate' : 'same',
            $bothTimed => $sameKind ? 'separate' : 'candidate',
            $sameKind && $a['ref'] !== null && $b['ref'] !== null => 'separate',
            $complementary => 'same',
            $sameKind => $oneToOne ? 'separate' : 'candidate',
            default => 'candidate',
        };
    }

    /**
     * What identifies the event a document describes, apart from the matching key.
     *
     * @return array{kind: string, ref: ?string, hash: ?string, moment: ?Carbon}
     */
    private function signals(AiDocument $document): array
    {
        $draft = is_array($document->processed_transaction_data) ? $document->processed_transaction_data : [];
        $raw = is_array($draft['raw'] ?? null) ? $draft['raw'] : [];

        $ref = is_string($raw['bank_reference'] ?? null) ? mb_strtolower(mb_trim($raw['bank_reference'])) : null;
        $date = is_string($draft['date'] ?? null) ? $draft['date'] : null;
        $time = is_string($raw['transaction_time'] ?? null) ? $raw['transaction_time'] : null;

        $moment = null;

        if ($date !== null && $time !== null) {
            try {
                $moment = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}") ?: null;
            } catch (Throwable) {
                // A malformed time (the model's output) is simply not a signal
            }
        }

        return [
            'kind' => $document->document_kind ?? 'other',
            'ref' => $ref === '' ? null : $ref,
            'hash' => $document->content_hash,
            'moment' => $moment,
        ];
    }
}
