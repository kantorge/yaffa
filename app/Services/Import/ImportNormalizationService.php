<?php

namespace App\Services\Import;

use App\Enums\TransactionType;
use DateTimeImmutable;

class ImportNormalizationService
{
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
                'category' => is_string($entry['category'] ?? null) ? $entry['category'] : null,
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
            ];
        }

        return $normalized;
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
            $normalized = str_replace(',', '.', $normalized);
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
}
