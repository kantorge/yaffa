<?php

namespace App\Services\Import;

use App\Enums\AiDocumentStatus;
use App\Enums\TransactionType;
use App\Models\AiDocument;
use App\Models\User;
use App\Services\AssetMatchingService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Throwable;

class ImportNormalizationService
{
    private const float IMPORT_PAYEE_SIMILARITY_THRESHOLD = 0.60;

    private const int RELATED_AI_DOCUMENT_LOOKBACK_DAYS = 45;

    private const int RELATED_AI_DOCUMENT_QUERY_LIMIT = 50;

    private const int RELATED_AI_DOCUMENT_RESULTS_PER_DRAFT = 3;

    private const float RELATED_AI_DOCUMENT_MIN_CONFIDENCE = 0.35;

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    public function normalizeQifEntries(array $entries, int $accountId): array
    {
        $normalized = [];

        foreach ($entries as $index => $entry) {
            $warnings = [];

            $normalizedDate = $this->normalizeDate(
                is_string($entry['date_raw'] ?? null) ? $entry['date_raw'] : null,
                $warnings,
            );
            $normalizedAmount = $this->normalizeAmount(
                is_string($entry['amount_raw'] ?? null) ? $entry['amount_raw'] : null,
                $warnings,
            );

            $warnings = array_values(array_unique(array_merge(
                is_array($entry['warnings'] ?? null) ? $entry['warnings'] : [],
                $warnings,
            )));

            $transactionType = $normalizedAmount !== null && $normalizedAmount < 0
                ? TransactionType::WITHDRAWAL
                : TransactionType::DEPOSIT;

            $absoluteAmount = $normalizedAmount !== null ? abs($normalizedAmount) : null;
            $status = $normalizedDate === null || $normalizedAmount === null
                ? 'failed_validation'
                : 'pending_review';

            $normalized[] = [
                'draft_index' => $index,
                'status' => $status,
                'source_type' => 'qif',
                'date' => $normalizedDate,
                'amount' => $absoluteAmount,
                'transaction_type' => $transactionType->value,
                'account_id' => $accountId,
                'payee' => is_string($entry['payee'] ?? null) ? $entry['payee'] : null,
                'memo' => is_string($entry['memo'] ?? null) ? $entry['memo'] : null,
                'source_category' => is_string($entry['category'] ?? null) && $entry['category'] !== ''
                    ? $entry['category']
                    : null,
                'reference' => is_string($entry['reference'] ?? null) ? $entry['reference'] : null,
                'raw_entry' => is_string($entry['raw_entry'] ?? null) ? $entry['raw_entry'] : null,
                'config' => [
                    'account_from_id' => $transactionType === TransactionType::WITHDRAWAL ? $accountId : null,
                    'account_to_id' => $transactionType === TransactionType::DEPOSIT ? $accountId : null,
                    'amount_from' => $transactionType === TransactionType::WITHDRAWAL ? $absoluteAmount : null,
                    'amount_to' => $transactionType === TransactionType::DEPOSIT ? $absoluteAmount : null,
                ],
                'warnings' => $warnings,
                'duplicate_candidates' => [],
                'schedule_candidates' => [],
                'related_ai_documents' => [],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $drafts
     * @return list<array<string, mixed>>
     */
    public function enrichDraftsWithPayeeMatches(User $user, array $drafts): array
    {
        $payees = $user->payees()->where('active', true)->select('id', 'name', 'alias')->get();

        if ($payees->isEmpty()) {
            foreach ($drafts as $index => $draft) {
                $drafts[$index]['matched_payee'] = null;
                $drafts[$index]['payee_cleaned'] = $this->cleanPayeeForDisplay(
                    is_string($draft['payee'] ?? null) ? $draft['payee'] : null,
                );
            }

            return $drafts;
        }

        // Pre-build a normalized name/alias → match result map for O(1) exact lookups.
        // Covers the common case where the CSV payee name matches an existing payee exactly
        // (case-insensitive), avoiding Jaro-Winkler for those drafts entirely.
        $exactLookup = [];
        foreach ($payees as $payee) {
            $displayName = $payee->name . ($payee->alias ? ' (' . $payee->alias . ')' : '');
            $result = [
                'id' => (int) $payee->id,
                'name' => $displayName,
                'similarity' => 1.0,
            ];

            $normalizedName = mb_strtolower(mb_trim((string) $payee->name));
            if ($normalizedName !== '' && ! isset($exactLookup[$normalizedName])) {
                $exactLookup[$normalizedName] = $result;
            }

            if ($payee->alias) {
                foreach (array_filter(array_map('trim', explode("\n", $payee->alias))) as $aliasLine) {
                    $normalizedAlias = mb_strtolower(mb_trim($aliasLine));
                    if ($normalizedAlias !== '' && ! isset($exactLookup[$normalizedAlias])) {
                        $exactLookup[$normalizedAlias] = $result;
                    }
                }
            }
        }

        $matchingService = new AssetMatchingService($user);

        foreach ($drafts as $index => $draft) {
            $rawPayee = is_string($draft['payee'] ?? null) && $draft['payee'] !== ''
                ? $draft['payee']
                : null;

            if ($rawPayee === null) {
                $drafts[$index]['matched_payee'] = null;
                $drafts[$index]['payee_cleaned'] = null;
                continue;
            }

            // Bank-generated payee text is often padded with account/reference numbers and
            // formatted amounts that dilute matching against short, clean payee names/aliases.
            // Strip that numeric noise before matching; fall back to the raw text if nothing remains.
            // The cleaned text is also surfaced on the draft for display, so the review UI can
            // show it instead of the raw noisy string while the raw entry stays untouched.
            $matchCandidate = (string) $this->cleanPayeeForDisplay($rawPayee);
            $drafts[$index]['payee_cleaned'] = $matchCandidate;

            // Fast path: exact case-insensitive match on name or alias.
            $normalizedRaw = mb_strtolower(mb_trim($matchCandidate));
            if ($normalizedRaw !== '' && isset($exactLookup[$normalizedRaw])) {
                $drafts[$index]['matched_payee'] = $exactLookup[$normalizedRaw];
                continue;
            }

            // Slow path: fuzzy Jaro-Winkler similarity.
            $match = $matchingService->matchBestPayeeFromCollection($matchCandidate, $payees);
            $drafts[$index]['matched_payee'] = ($match !== null && $match['similarity'] >= self::IMPORT_PAYEE_SIMILARITY_THRESHOLD)
                ? $match
                : null;
        }

        return $drafts;
    }

    /**
     * Produce the noise-stripped payee text used both for matching and for display, falling
     * back to the original raw text when stripping would leave nothing (e.g. an entry whose
     * payee text is entirely numeric).
     */
    private function cleanPayeeForDisplay(?string $rawPayee): ?string
    {
        if ($rawPayee === null || $rawPayee === '') {
            return null;
        }

        $stripped = $this->stripNumericNoise($rawPayee);

        return $stripped !== '' ? $stripped : $rawPayee;
    }

    /**
     * Strip structural numeric noise from bank-generated payee text before matching.
     *
     * Targets patterns identifiable by shape rather than vocabulary, so it generalizes across
     * banks/locales without a maintained per-bank stopword list: formatted amounts (e.g.
     * "22.515,00") and long digit runs (account numbers, reference codes, YYYYMMDD dates).
     * Currency codes and language-specific boilerplate words are intentionally left untouched.
     */
    private function stripNumericNoise(string $value): string
    {
        // Formatted amounts: digits grouped with thousand/decimal separators (.,) e.g. "22.515,00".
        $stripped = preg_replace('/\d+(?:[.,]\d+)+/u', ' ', $value) ?? $value;

        // Long standalone digit runs: account numbers, reference codes, YYYYMMDD dates.
        $stripped = preg_replace('/\d{6,}/u', ' ', $stripped) ?? $stripped;

        return mb_trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);
    }

    /**
     * @param  list<array<string, mixed>>  $drafts
     * @return list<array<string, mixed>>
     */
    public function enrichDraftsWithRelatedAiDocuments(User $user, array $drafts): array
    {
        $candidates = $this->loadRelatedAiDocumentCandidates($user, $drafts);

        foreach ($drafts as $index => $draft) {
            $drafts[$index]['related_ai_documents'] = $this->matchRelatedAiDocumentsForDraft($draft, $candidates);
        }

        return $drafts;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function normalizeDate(?string $rawDate, array &$warnings): ?string
    {
        if ($rawDate === null || mb_trim($rawDate) === '') {
            $warnings[] = 'Missing date value.';
            return null;
        }

        $value = mb_trim($rawDate);
        $this->appendAmbiguousDateWarning($value, $warnings);

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd.m.Y',
            'd.m.y',
            'Y.m.d',
            'd/m/Y',
            'm/d/Y',
            'd M y',
            'j M y',
            'd M Y',
            'j M Y',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        $warnings[] = sprintf('Invalid date format "%s".', $value);

        return null;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function normalizeAmount(?string $rawAmount, array &$warnings): ?float
    {
        if ($rawAmount === null || mb_trim($rawAmount) === '') {
            $warnings[] = 'Missing amount value.';
            return null;
        }

        $value = mb_trim($rawAmount);
        $normalized = str_replace(["\u{00A0}", ' ', "'"], '', $value);

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = mb_strrpos($normalized, ',');
            $lastDot = mb_strrpos($normalized, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            if (preg_match('/,\d{3}$/', $normalized)) {
                // Comma in thousands position (e.g. 1,234) — remove it
                $normalized = str_replace(',', '', $normalized);
            } else {
                // Comma as decimal separator (e.g. 1,23 or 1,2) — replace with dot
                $normalized = str_replace(',', '.', $normalized);
            }
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            $warnings[] = sprintf('Invalid amount format "%s".', $value);
            return null;
        }

        $amount = (float) $normalized;

        return abs($amount) < 0.0000001 ? 0.0 : $amount;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function appendAmbiguousDateWarning(string $value, array &$warnings): void
    {
        $parts = explode('/', $value);

        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            return;
        }

        $first = (int) $parts[0];
        $second = (int) $parts[1];

        if ($first >= 1 && $first <= 12 && $second >= 1 && $second <= 12) {
            $warnings[] = sprintf(
                'Ambiguous date format "%s" was parsed using day/month interpretation.',
                $value,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $drafts
     * @return list<array<string, mixed>>
     */
    private function loadRelatedAiDocumentCandidates(User $user, array $drafts): array
    {
        $draftDates = collect($drafts)
            ->pluck('date')
            ->filter(fn (mixed $date): bool => is_string($date) && $date !== '')
            ->map(function (string $date): ?CarbonImmutable {
                try {
                    return CarbonImmutable::parse($date);
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter();

        $fromDate = $draftDates->isNotEmpty()
            ? $draftDates->min()->subDays(self::RELATED_AI_DOCUMENT_LOOKBACK_DAYS)
            : now()->subDays(self::RELATED_AI_DOCUMENT_LOOKBACK_DAYS);

        $documents = AiDocument::query()
            ->where('user_id', $user->id)
            ->where('status', AiDocumentStatus::ReadyForReview->value)
            ->where(function ($query) use ($fromDate): void {
                $query->where('processed_at', '>=', $fromDate)
                    ->orWhere('created_at', '>=', $fromDate);
            })
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->limit(self::RELATED_AI_DOCUMENT_QUERY_LIMIT)
            ->select(['id', 'status', 'processed_transaction_data', 'processed_at', 'created_at'])
            ->get();

        return $documents
            ->map(function (AiDocument $document): array {
                $payload = is_array($document->processed_transaction_data)
                    ? $document->processed_transaction_data
                    : [];

                return [
                    'document' => $document,
                    'merchant' => $this->extractDocumentMerchant($payload),
                    'amount' => $this->extractDocumentAmount($payload),
                    'date' => $this->extractDocumentDate($payload),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function matchRelatedAiDocumentsForDraft(array $draft, array $candidates): array
    {
        $scored = [];

        foreach ($candidates as $candidate) {
            $score = 0.0;
            $matchedOn = [];

            $draftAmount = is_numeric($draft['amount'] ?? null) ? (float) $draft['amount'] : null;
            $candidateAmount = is_numeric($candidate['amount'] ?? null) ? (float) $candidate['amount'] : null;
            if ($draftAmount !== null && $candidateAmount !== null) {
                $difference = abs($draftAmount - $candidateAmount);
                $tolerance = max(0.01, $draftAmount * 0.02);

                if ($difference < 0.01) {
                    $score += 0.55;
                    $matchedOn[] = 'amount';
                } elseif ($difference <= $tolerance) {
                    $score += 0.35;
                    $matchedOn[] = 'amount';
                }
            }

            $draftDate = null;
            if (is_string($draft['date'] ?? null) && $draft['date'] !== '') {
                try {
                    $draftDate = CarbonImmutable::parse($draft['date']);
                } catch (Throwable) {
                }
            }
            $candidateDate = is_string($candidate['date'] ?? null) ? CarbonImmutable::parse($candidate['date']) : null;
            if ($draftDate instanceof CarbonImmutable && $candidateDate instanceof CarbonImmutable) {
                $daysApart = abs($draftDate->diffInDays($candidateDate, false));

                if ($daysApart === 0.0) {
                    $score += 0.25;
                    $matchedOn[] = 'date';
                } elseif ($daysApart <= 3) {
                    $score += 0.18;
                    $matchedOn[] = 'date';
                } elseif ($daysApart <= 7) {
                    $score += 0.1;
                    $matchedOn[] = 'date';
                }
            }

            $draftPayee = $this->normalizeComparableText(is_string($draft['payee'] ?? null) ? $draft['payee'] : null);
            $candidateMerchant = $this->normalizeComparableText(is_string($candidate['merchant'] ?? null) ? $candidate['merchant'] : null);
            if ($draftPayee !== null && $candidateMerchant !== null) {
                similar_text($draftPayee, $candidateMerchant, $similarityPercent);

                if ($draftPayee === $candidateMerchant) {
                    $score += 0.2;
                    $matchedOn[] = 'payee';
                } elseif (str_contains($candidateMerchant, $draftPayee) || str_contains($draftPayee, $candidateMerchant) || $similarityPercent >= 70.0) {
                    $score += 0.12;
                    $matchedOn[] = 'payee';
                }
            }

            if ($matchedOn === []) {
                continue;
            }

            if ($score < self::RELATED_AI_DOCUMENT_MIN_CONFIDENCE && count($matchedOn) < 2) {
                continue;
            }

            /** @var AiDocument $document */
            $document = $candidate['document'];
            $scored[] = [
                'ai_document_id' => $document->id,
                'status' => $document->status,
                'confidence_score' => round(min(1.0, $score), 3),
                'matched_on' => array_values(array_unique($matchedOn)),
                'summary' => [
                    'merchant' => $candidate['merchant'],
                    'total_amount' => $candidate['amount'],
                    'document_date' => $candidate['date'],
                ],
            ];
        }

        usort($scored, fn (array $left, array $right): int => $right['confidence_score'] <=> $left['confidence_score']);

        return array_slice($scored, 0, self::RELATED_AI_DOCUMENT_RESULTS_PER_DRAFT);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractDocumentMerchant(array $payload): ?string
    {
        $merchant = $payload['merchant']
            ?? $payload['payee']
            ?? data_get($payload, 'raw.merchant')
            ?? data_get($payload, 'raw.payee')
            ?? data_get($payload, 'matched_entities.payee.name');

        return is_string($merchant) && mb_trim($merchant) !== ''
            ? mb_trim($merchant)
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractDocumentAmount(array $payload): ?float
    {
        $amount = data_get($payload, 'config.amount_from')
            ?? data_get($payload, 'config.amount_to')
            ?? $payload['amount']
            ?? $payload['total_amount']
            ?? data_get($payload, 'raw.amount');

        return is_numeric($amount) ? abs((float) $amount) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractDocumentDate(array $payload): ?string
    {
        $date = $payload['date'] ?? $payload['document_date'] ?? data_get($payload, 'raw.date');

        if (! is_string($date) || mb_trim($date) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeComparableText(?string $value): ?string
    {
        if ($value === null || mb_trim($value) === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/iu', ' ', mb_strtolower(mb_trim($value)));

        return is_string($normalized) ? mb_trim($normalized) : null;
    }
}
