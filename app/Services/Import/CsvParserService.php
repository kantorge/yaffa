<?php

namespace App\Services\Import;

use App\Enums\ImportCanonicalField;
use App\Enums\TransactionType;
use App\Models\AccountEntity;
use App\Models\FileImportProfile;
use Illuminate\Http\UploadedFile;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use RuntimeException;
use DateTimeImmutable;

class CsvParserService
{
    /** @var array<string, int>|null */
    private ?array $payeeLookup = null;

    /**
     * @return array{drafts: list<array<string, mixed>>, warnings: list<string>, unmatched_rows: list<array<string, mixed>>}
     */
    public function parseFile(UploadedFile $file, FileImportProfile $profile, int $accountId, int $userId): array
    {
        $path = $file->getRealPath();
        if (! is_string($path)) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        [$normalizedContent, $encodingWarning] = $this->normalizeContentToUtf8($content);
        unset($content);

        $tmpPath = tempnam(sys_get_temp_dir(), 'csv_import_');
        if ($tmpPath === false) {
            throw new RuntimeException('Unable to create temporary file for CSV parsing.');
        }

        try {
            file_put_contents($tmpPath, $normalizedContent);
            unset($normalizedContent);

            $reader = Reader::createFromPath($tmpPath);
            $reader->setDelimiter((string) ($profile->delimiter ?: ','));

            if ($profile->has_header_row) {
                $reader->setHeaderOffset(0);
            }

            try {
                $records = $reader->getRecords();
            } catch (CsvException $e) {
                throw new RuntimeException($this->humanizeCsvException($e));
            }

            $this->loadPayeeLookup($userId);

            $maxRows = max(1, (int) config('yaffa.import_max_rows', 5000));

            $warnings = [];
            if ($encodingWarning !== null) {
                $warnings[] = $encodingWarning;
            }

            $drafts = [];
            $unmatchedRows = [];

            $rowIndex = 0;
            try {
                foreach ($records as $record) {
                    $rowIndex++;
                    if ($rowIndex > $maxRows) {
                        throw new RuntimeException(
                            sprintf('CSV import exceeds the configured maximum row count of %d.', $maxRows),
                        );
                    }

                    $canonicalFacts = $this->canonicalizeRow($record, (array) ($profile->mapping_json ?? []));
                    $rawEntry = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

                    $trimStrings = (bool) data_get($profile->options_json, 'parser_settings.trim_strings', true);
                    if ($trimStrings) {
                        $canonicalFacts = array_map(
                            fn (mixed $value) => is_string($value) ? mb_trim($value) : $value,
                            $canonicalFacts,
                        );
                    }

                    $skipEmptyRows = (bool) data_get($profile->options_json, 'parser_settings.skip_empty_rows', true);
                    if ($skipEmptyRows && $this->isEmptyRow($canonicalFacts)) {
                        continue;
                    }

                    if ($profile->type === 'system') {
                        $parsedRow = $this->parseSystemRow(
                            canonicalFacts: $canonicalFacts,
                            profile: $profile,
                            accountId: $accountId,
                            userId: $userId,
                            draftIndex: count($drafts),
                            rawEntry: $rawEntry,
                        );
                    } else {
                        $parsedRow = $this->parseUserRow(
                            canonicalFacts: $canonicalFacts,
                            originalRecord: $record,
                            profile: $profile,
                            accountId: $accountId,
                            draftIndex: count($drafts),
                            rawEntry: $rawEntry,
                        );
                    }

                    if ($parsedRow['matched'] === false) {
                        $unmatchedRows[] = [
                            'row_index' => $rowIndex,
                            'raw_entry' => $rawEntry,
                            'warnings' => $parsedRow['warnings'],
                        ];
                    }

                    $drafts[] = $parsedRow['draft'];
                }
            } catch (RuntimeException $e) {
                throw $e;
            } catch (CsvException $e) {
                throw new RuntimeException($this->humanizeCsvException($e));
            }

            return [
                'drafts' => $drafts,
                'warnings' => array_values(array_unique($warnings)),
                'unmatched_rows' => $unmatchedRows,
            ];
        } finally {
            @unlink($tmpPath);
        }
    }

    private function humanizeCsvException(CsvException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'duplicate')) {
            return 'The CSV file contains duplicate column names in the header row. Please check the file and ensure all column headers are unique.';
        }

        if (str_contains($message, 'header')) {
            return 'The CSV header row could not be read. Please verify the file format and delimiter settings in the selected profile.';
        }

        return 'The CSV file could not be parsed. Please check that the file format and delimiter match the selected profile settings.';
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    private function canonicalizeRow(array $record, array $mapping): array
    {
        $canonical = [];

        foreach ($record as $header => $value) {
            $headerKey = $header;
            $targetKey = $mapping[$headerKey] ?? $headerKey;

            if ($targetKey !== '' && $targetKey !== ImportCanonicalField::Ignore->value) {
                $canonical[$targetKey] = $value;
            }
        }

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $canonicalFacts
     * @return array{matched: bool, warnings: list<string>, draft: array<string, mixed>}
     */
    private function parseSystemRow(
        array $canonicalFacts,
        FileImportProfile $profile,
        int $accountId,
        int $userId,
        int $draftIndex,
        string $rawEntry,
    ): array {
        $warnings = [];
        $output = [];
        $matched = false;

        /** @var list<array<string, mixed>> $matchingRules */
        $matchingRules = (array) data_get($profile->options_json, 'matching_rules', []);

        foreach ($matchingRules as $rule) {
            $conditions = (array) ($rule['conditions'] ?? []);
            if (! $this->matchesConditions($canonicalFacts, $output, $conditions)) {
                continue;
            }

            $matched = true;
            $actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];

            foreach ($actions as $action) {
                $this->applyAction($output, $canonicalFacts, $action, $warnings, $accountId, $userId, $profile);
            }

            break;
        }

        if (! $matched) {
            $warnings[] = (string) data_get(
                $profile->options_json,
                'warnings.unmatched_row',
                'No matching system rule was found for this row.',
            );
        }

        $defaults = (array) data_get($profile->options_json, 'defaults', []);
        $output = $this->applyDefaults($output, $defaults);

        $draft = $this->buildDraftFromOutput(
            output: $output,
            canonicalFacts: $canonicalFacts,
            accountId: $accountId,
            draftIndex: $draftIndex,
            rawEntry: $rawEntry,
            warnings: $warnings,
            sourceType: 'csv',
            profile: $profile,
        );

        return [
            'matched' => $matched,
            'warnings' => $warnings,
            'draft' => $draft,
        ];
    }

    /**
     * @param  array<string, mixed>  $canonicalFacts
     * @param  array<int|string, mixed>  $originalRecord
     * @return array{matched: bool, warnings: list<string>, draft: array<string, mixed>}
     */
    private function parseUserRow(
        array $canonicalFacts,
        array $originalRecord,
        FileImportProfile $profile,
        int $accountId,
        int $draftIndex,
        string $rawEntry,
    ): array {
        $warnings = [];

        $rawAmount = $canonicalFacts['amount'] ?? null;
        $amount = $this->transformParseLocalizedAmount(
            $rawAmount,
            [
                'absolute_value' => true,
                'decimal_separator' => $profile->decimal_separator,
                'thousand_separator' => $profile->thousand_separator,
            ],
            $warnings,
        );

        $date = $this->transformParseDate(
            $canonicalFacts['date'] ?? null,
            ['format' => $profile->date_format ?: 'Y-m-d'],
            $warnings,
        );

        $rawAmountForTypeDetection = $rawAmount;
        if ($profile->sign_handling === 'inverted' && is_string($rawAmount)) {
            $trimmed = mb_trim($rawAmount);
            $rawAmountForTypeDetection = str_starts_with($trimmed, '-')
                ? mb_substr($trimmed, 1)
                : '-' . $trimmed;
        }

        $transactionType = $this->resolveTransactionType(
            is_string($canonicalFacts['transaction_type'] ?? null) ? $canonicalFacts['transaction_type'] : null,
            $rawAmountForTypeDetection,
            $warnings,
        );

        $memo = $this->buildUserProfileMemo($originalRecord, $profile);

        $output = [
            'config_type' => 'standard',
            'date' => $date,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'payee' => is_string($canonicalFacts['payee'] ?? null) ? $canonicalFacts['payee'] : null,
            'memo' => $memo,
            'source_category' => is_string($canonicalFacts['category'] ?? null) && $canonicalFacts['category'] !== ''
                ? $canonicalFacts['category']
                : null,
        ];

        if ($transactionType === TransactionType::DEPOSIT->value) {
            $output['config']['account_to_id'] = $accountId;
            $output['config']['account_from_id'] = null;
        } else {
            $output['config']['account_from_id'] = $accountId;
            $output['config']['account_to_id'] = null;
        }

        $output['config']['amount_from'] = $amount;
        $output['config']['amount_to'] = $amount;

        $draft = $this->buildDraftFromOutput(
            output: $output,
            canonicalFacts: $canonicalFacts,
            accountId: $accountId,
            draftIndex: $draftIndex,
            rawEntry: $rawEntry,
            warnings: $warnings,
            sourceType: 'csv',
            profile: $profile,
        );

        return [
            'matched' => true,
            'warnings' => $warnings,
            'draft' => $draft,
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $canonicalFacts
     * @param  array<string, mixed>  $action
     * @param  list<string>  $warnings
     */
    private function applyAction(
        array &$output,
        array $canonicalFacts,
        array $action,
        array &$warnings,
        int $accountId,
        int $userId,
        FileImportProfile $profile,
    ): void {
        $type = (string) ($action['type'] ?? '');
        $target = (string) ($action['target'] ?? '');

        if ($target === '') {
            $warnings[] = 'Action target is missing.';
            return;
        }

        if ($type === 'set') {
            data_set($output, $target, $action['value'] ?? null);
            return;
        }

        if ($type === 'copy') {
            $source = (string) ($action['source'] ?? '');
            data_set($output, $target, $canonicalFacts[$source] ?? null);
            return;
        }

        if ($type === 'map_transform') {
            $source = (string) ($action['source'] ?? '');
            $transform = (string) ($action['transform'] ?? '');
            $args = is_array($action['args'] ?? null) ? $action['args'] : [];
            $value = $this->applyTransform($transform, $canonicalFacts[$source] ?? null, $args, $warnings, $accountId, $userId, $profile);
            data_set($output, $target, $value);
            return;
        }

        if ($type === 'apply_transform') {
            $transform = (string) ($action['transform'] ?? '');
            $args = is_array($action['args'] ?? null) ? $action['args'] : [];
            $value = $this->applyTransform($transform, null, $args, $warnings, $accountId, $userId, $profile);
            data_set($output, $target, $value);
            return;
        }

        if ($type === 'conditional_copy') {
            $when = is_array($action['when'] ?? null) ? $action['when'] : [];
            if (! $this->matchesSingleCondition($canonicalFacts, $output, $when)) {
                return;
            }

            $source = (string) ($action['source'] ?? '');
            data_set($output, $target, $canonicalFacts[$source] ?? null);
            return;
        }

        $warnings[] = sprintf('Unsupported action type "%s".', $type);
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function matchesConditions(array $facts, array $output, array $conditions): bool
    {
        if (array_key_exists('all', $conditions)) {
            $all = is_array($conditions['all']) ? $conditions['all'] : [];
            foreach ($all as $condition) {
                if (! is_array($condition) || ! $this->matchesSingleCondition($facts, $output, $condition)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $conditions)) {
            $any = is_array($conditions['any']) ? $conditions['any'] : [];
            foreach ($any as $conditionOrGroup) {
                if (! is_array($conditionOrGroup)) {
                    continue;
                }

                $isGroup = array_key_exists('all', $conditionOrGroup) || array_key_exists('any', $conditionOrGroup);
                if ($isGroup && $this->matchesConditions($facts, $output, $conditionOrGroup)) {
                    return true;
                }

                if (! $isGroup && $this->matchesSingleCondition($facts, $output, $conditionOrGroup)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function matchesSingleCondition(array $facts, array $output, array $condition): bool
    {
        $fact = (string) ($condition['fact'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'equal');
        $expected = $condition['value'] ?? null;

        $actual = $facts[$fact] ?? data_get($output, $fact);

        if ($operator === 'equal') {
            return (string) $actual === (string) $expected;
        }

        if ($operator === 'in') {
            if (! is_array($expected)) {
                return false;
            }

            return in_array((string) $actual, array_map(static fn (mixed $value) => (string) $value, $expected), true);
        }

        if ($operator === 'matches_regex') {
            if (! is_string($expected) || $expected === '') {
                return false;
            }

            if (! is_string($actual)) {
                return false;
            }

            $result = @preg_match('/' . $expected . '/u', $actual);

            return $result === 1 && preg_last_error() === PREG_NO_ERROR;
        }

        if ($operator === 'starts_with') {
            return is_string($actual) && is_string($expected) && str_starts_with($actual, $expected);
        }

        if ($operator === 'ends_with') {
            return is_string($actual) && is_string($expected) && str_ends_with($actual, $expected);
        }

        if ($operator === 'contains') {
            return is_string($actual) && is_string($expected) && str_contains($actual, $expected);
        }

        if ($operator === 'amount_sign_is') {
            $rawAmount = $actual;
            $normalized = is_numeric($rawAmount) ? (float) $rawAmount : (float) str_replace(',', '.', (string) $rawAmount);

            return match ((string) $expected) {
                'positive' => $normalized > 0,
                'negative' => $normalized < 0,
                'zero' => abs($normalized) < 0.0000001,
                default => false,
            };
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $warnings
     */
    private function applyTransform(
        string $transform,
        mixed $value,
        array $args,
        array &$warnings,
        int $accountId,
        int $userId,
        FileImportProfile $profile,
    ): mixed {
        return match ($transform) {
            'parse_localized_amount' => $this->transformParseLocalizedAmount($value, array_merge($args, [
                'decimal_separator' => $profile->decimal_separator,
                'thousand_separator' => $profile->thousand_separator,
            ]), $warnings),
            'parse_date' => $this->transformParseDate($value, $args, $warnings),
            'extract_date_regex' => $this->transformExtractDateRegex($value, $args, $warnings),
            'selected_account_context' => $accountId,
            'resolve_payee_by_name_or_alias' => $this->transformResolvePayeeByNameOrAlias($value, $args, $userId, $warnings),
            'normalize_whitespace' => $this->transformNormalizeWhitespace($value),
            'invert_sign' => $this->transformInvertSign($value),
            'to_lowercase' => is_string($value) ? mb_strtolower($value) : $value,
            'to_uppercase' => is_string($value) ? mb_strtoupper($value) : $value,
            default => $this->unsupportedTransform($transform, $warnings),
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $warnings
     */
    private function transformParseLocalizedAmount(mixed $value, array $args, array &$warnings): ?float
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $warnings[] = 'Amount is missing or invalid.';
            return null;
        }

        $raw = is_string($value) ? mb_trim($value) : (string) $value;
        if ($raw === '') {
            $warnings[] = 'Amount is empty.';
            return null;
        }

        $thousandSeparator = (string) ($args['thousand_separator'] ?? ' ');
        $decimalSeparator = (string) ($args['decimal_separator'] ?? ',');

        $normalized = str_replace(["\u{00A0}", "\u{2009}", "\u{202F}", "'"], '', $raw);
        // Strip trailing 3-letter uppercase currency code (e.g. "HUF", "EUR", "USD") using ASCII-safe pattern
        if (preg_match('/[A-Z]{3}$/', $normalized) === 1) {
            $normalized = mb_rtrim(mb_substr($normalized, 0, -3));
        }
        if ($thousandSeparator !== '') {
            $normalized = str_replace($thousandSeparator, '', $normalized);
        }

        if ($decimalSeparator !== '.' && $decimalSeparator !== '') {
            $normalized = str_replace($decimalSeparator, '.', $normalized);
        }

        $normalized = str_replace(' ', '', $normalized);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            $warnings[] = sprintf('Invalid localized amount "%s".', $raw);
            return null;
        }

        $amount = (float) $normalized;

        if ((bool) ($args['absolute_value'] ?? false)) {
            $amount = abs($amount);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $warnings
     */
    private function transformParseDate(mixed $value, array $args, array &$warnings): ?string
    {
        if (! is_string($value)) {
            $warnings[] = 'Date value is missing.';
            return null;
        }

        $format = (string) ($args['format'] ?? 'Y-m-d');
        // The '+' suffix tells PHP 8.2+ to treat trailing content (e.g. weekday names such as
        // ", szerda") as a warning rather than a hard parse failure that returns false.
        $parsed = DateTimeImmutable::createFromFormat('!' . $format . '+', mb_trim($value));

        if ($parsed === false) {
            $warnings[] = sprintf('Date value "%s" does not match format %s.', $value, $format);
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && $errors['error_count'] > 0) {
            $warnings[] = sprintf('Date value "%s" contains an out-of-range date component.', $value);
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $warnings
     */
    private function transformExtractDateRegex(mixed $value, array $args, array &$warnings): ?string
    {
        if (! is_string($value)) {
            $warnings[] = 'Date extraction source value is missing.';
            return null;
        }

        $pattern = (string) ($args['pattern'] ?? '');
        if ($pattern === '') {
            $warnings[] = 'Date extraction pattern is missing.';
            return null;
        }

        $matchResult = @preg_match('/' . $pattern . '/u', $value, $matches);

        if ($matchResult === false || preg_last_error() !== PREG_NO_ERROR) {
            $warnings[] = 'Date extraction pattern is invalid.';
            return null;
        }

        if ($matchResult !== 1) {
            $warnings[] = sprintf('Date extraction pattern did not match value "%s".', $value);
            return null;
        }

        if (isset($matches[1], $matches[2], $matches[3])) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if (! checkdate($month, $day, $year)) {
                $warnings[] = sprintf('Date extraction produced an invalid date "%s-%s-%s".', $matches[1], $matches[2], $matches[3]);
                return null;
            }

            return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        $warnings[] = 'Date extraction matched but capture groups were incomplete.';

        return null;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $warnings
     */
    private function transformResolvePayeeByNameOrAlias(
        mixed $value,
        array $args,
        int $userId,
        array &$warnings,
    ): ?int {
        $candidate = is_string($value) ? mb_trim($value) : '';

        if ($candidate === '') {
            $candidate = (string) ($args['fallback_payee_name'] ?? '');
        }

        if ($candidate === '') {
            $warnings[] = 'Payee source text is empty and no fallback was provided.';
            return null;
        }

        return ($this->payeeLookup ?? [])[mb_strtolower($candidate)] ?? null;
    }

    private function loadPayeeLookup(int $userId): void
    {
        $this->payeeLookup = [];

        $payees = AccountEntity::query()
            ->where('user_id', $userId)
            ->where('config_type', 'payee')
            ->select(['id', 'name', 'alias'])
            ->get();

        foreach ($payees as $payee) {
            $lowerName = mb_strtolower((string) $payee->name);
            if ($lowerName !== '' && ! isset($this->payeeLookup[$lowerName])) {
                $this->payeeLookup[$lowerName] = (int) $payee->id;
            }

            $alias = $payee->alias;
            if (is_string($alias) && $alias !== '') {
                $lowerAlias = mb_strtolower($alias);
                if (! isset($this->payeeLookup[$lowerAlias])) {
                    $this->payeeLookup[$lowerAlias] = (int) $payee->id;
                }
            }
        }
    }

    private function transformNormalizeWhitespace(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/\s+/u', ' ', mb_trim($value));
    }

    private function transformInvertSign(mixed $value): mixed
    {
        if (! is_numeric($value)) {
            return $value;
        }

        return (float) $value * -1;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function unsupportedTransform(string $transform, array &$warnings): null
    {
        $warnings[] = sprintf('Unsupported transform "%s".', $transform);

        return null;
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function applyDefaults(array $output, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (! data_get($output, (string) $key)) {
                data_set($output, (string) $key, $value);
            }
        }

        return $output;
    }

    /**
     * Concatenates all comment and reference column values for a user-profile row.
     * Separator is configurable via options_json.comment_separator (defaults to " | ").
     *
     * @param  array<int|string, mixed>  $originalRecord
     */
    private function buildUserProfileMemo(array $originalRecord, FileImportProfile $profile): ?string
    {
        $separator = (string) data_get($profile->options_json, 'comment_separator', ' | ');
        $mapping = (array) ($profile->mapping_json ?? []);

        $parts = [];
        foreach ($originalRecord as $header => $value) {
            $target = $mapping[(string) $header] ?? null;
            if (($target === 'comment' || $target === 'reference') && is_string($value) && mb_trim($value) !== '') {
                $parts[] = mb_trim($value);
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode($separator, $parts);
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $canonicalFacts
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function buildDraftFromOutput(
        array $output,
        array $canonicalFacts,
        int $accountId,
        int $draftIndex,
        string $rawEntry,
        array $warnings,
        string $sourceType,
        FileImportProfile $profile,
    ): array {
        $date = is_string($output['date'] ?? null) ? $output['date'] : null;

        $amount = null;
        if (is_float($output['amount'] ?? null) || is_int($output['amount'] ?? null)) {
            $amount = (float) $output['amount'];
        }

        if ($amount === null && isset($output['config']['amount_from']) && is_numeric($output['config']['amount_from'])) {
            $amount = (float) $output['config']['amount_from'];
        }

        $transactionType = $this->resolveTransactionType(
            is_string($output['transaction_type'] ?? null) ? $output['transaction_type'] : null,
            $canonicalFacts['amount'] ?? null,
            $warnings,
        );

        $accountFromId = data_get($output, 'config.account_from_id');
        $accountToId = data_get($output, 'config.account_to_id');

        if (! is_int($accountFromId) && null !== $accountFromId) {
            $accountFromId = null;
        }

        if (! is_int($accountToId) && null !== $accountToId) {
            $accountToId = null;
        }

        if ($accountFromId === null && $accountToId === null) {
            if ($transactionType === TransactionType::DEPOSIT->value) {
                $accountToId = $accountId;
            } else {
                $accountFromId = $accountId;
            }
        }

        $finalAmount = $amount !== null ? abs($amount) : null;

        $status = $date === null || $finalAmount === null || $transactionType === null
            ? 'failed_validation'
            : 'pending_review';

        return [
            'draft_index' => $draftIndex,
            'status' => $status,
            'source_type' => $sourceType,
            'file_import_profile_id' => $profile->id,
            'date' => $date,
            'amount' => $finalAmount,
            'transaction_type' => $transactionType,
            'config_type' => is_string($output['config_type'] ?? null) ? $output['config_type'] : 'standard',
            'account_id' => $accountId,
            'payee' => is_string($output['payee'] ?? null) ? $output['payee'] : null,
            'memo' => is_string($output['memo'] ?? null) ? $output['memo'] : null,
            'source_category' => is_string($output['source_category'] ?? null) && $output['source_category'] !== ''
                ? $output['source_category']
                : null,
            'raw_entry' => $rawEntry,
            'config' => [
                'account_from_id' => $accountFromId,
                'account_to_id' => $accountToId,
                'amount_from' => is_numeric(data_get($output, 'config.amount_from')) ? (float) data_get($output, 'config.amount_from') : $finalAmount,
                'amount_to' => is_numeric(data_get($output, 'config.amount_to')) ? (float) data_get($output, 'config.amount_to') : $finalAmount,
            ],
            'warnings' => array_values(array_unique($warnings)),
            'duplicate_candidates' => [],
            'related_ai_documents' => [],
        ];
    }

    /**
     * @param  list<string>  $warnings
     */
    private function resolveTransactionType(?string $explicitType, mixed $rawAmount, array &$warnings): ?string
    {
        if (is_string($explicitType) && $explicitType !== '') {
            if (in_array($explicitType, [
                TransactionType::WITHDRAWAL->value,
                TransactionType::DEPOSIT->value,
                TransactionType::TRANSFER->value,
            ], true)) {
                return $explicitType;
            }

            $warnings[] = sprintf('Unsupported transaction type "%s".', $explicitType);
        }

        $raw = is_string($rawAmount) ? mb_trim($rawAmount) : (is_numeric($rawAmount) ? (string) $rawAmount : '');
        if ($raw === '') {
            return null;
        }

        return str_starts_with($raw, '-')
            ? TransactionType::WITHDRAWAL->value
            : TransactionType::DEPOSIT->value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && mb_trim($value) !== '') {
                return false;
            }

            if (! is_string($value) && $value !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function normalizeContentToUtf8(string $content): array
    {
        if ($content === '' || mb_check_encoding($content, 'UTF-8')) {
            return [$content, null];
        }

        $supportedEncodings = ['UTF-8', 'Windows-1252', 'ISO-8859-2', 'ISO-8859-1'];
        $detectedEncoding = mb_detect_encoding($content, $supportedEncodings, true);

        if (is_string($detectedEncoding) && $detectedEncoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);

            if (mb_check_encoding($converted, 'UTF-8')) {
                return [
                    $converted,
                    sprintf('Import file encoding (%s) was converted to UTF-8 before parsing.', $detectedEncoding),
                ];
            }
        }

        foreach (['Windows-1252', 'ISO-8859-2', 'ISO-8859-1'] as $fallbackEncoding) {
            $converted = mb_convert_encoding($content, 'UTF-8', $fallbackEncoding);

            if (mb_check_encoding($converted, 'UTF-8')) {
                return [
                    $converted,
                    sprintf('Import file encoding was converted to UTF-8 using fallback %s.', $fallbackEncoding),
                ];
            }
        }

        $sanitized = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        return [
            $sanitized,
            'Import file contained invalid encoding bytes and was sanitized to UTF-8 before parsing.',
        ];
    }
}
