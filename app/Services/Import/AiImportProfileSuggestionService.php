<?php

namespace App\Services\Import;

use App\Enums\ImportCanonicalField;
use App\Models\AccountEntity;
use App\Models\AiProviderConfig;
use Exception;
use Illuminate\Support\Facades\Log;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use RuntimeException;

class AiImportProfileSuggestionService
{
    private const int MAX_SAMPLE_DATA_ROWS = 10;

    /**
     * Generate a structured import profile suggestion from a CSV sample.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the uploaded content is not parseable as CSV.
     * @throws \App\Exceptions\AiProviderFailureException When the AI provider call fails.
     */
    public function suggest(AiProviderConfig $config, string $csvContent, ?int $accountId = null): array
    {
        $originalLength = mb_strlen($csvContent);
        $isUtf8Before = mb_check_encoding($csvContent, 'UTF-8');

        [$trimmedCsv] = $this->trimCsvToSampleRows($csvContent);

        Log::info('AiImportProfileSuggestionService: suggest() called', [
            'provider' => $config->provider,
            'model' => $config->model,
            'account_id' => $accountId,
            'original_content_length' => $originalLength,
            'was_utf8_before_normalize' => $isUtf8Before,
            'trimmed_csv_length' => mb_strlen($trimmedCsv),
            'trimmed_csv_is_utf8' => mb_check_encoding($trimmedCsv, 'UTF-8'),
        ]);

        $prompt = $this->buildPrompt($trimmedCsv, $accountId);

        $response = $this->callAiProvider($config, $prompt);

        return $this->sanitizeResponse($response);
    }

    /**
     * @return array{0: string, 1: list<string>}
     *
     * @throws RuntimeException When content is not parseable as CSV.
     */
    public function trimCsvToSampleRows(string $csvContent): array
    {
        $csvContent = $this->normalizeToUtf8($csvContent);

        try {
            $reader = Reader::fromString($csvContent);
            $reader->setHeaderOffset(0);

            $headers = $reader->getHeader();
            $rows = [];

            foreach ($reader->getRecords() as $record) {
                $rows[] = $record;
                if (count($rows) >= self::MAX_SAMPLE_DATA_ROWS) {
                    break;
                }
            }
        } catch (CsvException $e) {
            throw new RuntimeException('The uploaded file could not be parsed as a CSV file.', 0, $e);
        }

        if (empty($headers)) {
            throw new RuntimeException('The uploaded CSV file has no detectable header row.');
        }

        $output = implode(',', array_map(
            fn (string $h) => '"' . str_replace('"', '""', $h) . '"',
            $headers,
        )) . "\n";

        foreach ($rows as $row) {
            $output .= implode(',', array_map(
                fn (mixed $v) => '"' . str_replace('"', '""', (string) $v) . '"',
                $row,
            )) . "\n";
        }

        return [$output, array_values($headers)];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\AiProviderFailureException
     */
    private function callAiProvider(AiProviderConfig $config, string $prompt): array
    {
        Log::info('AiImportProfileSuggestionService: calling AI provider', [
            'provider' => $config->provider,
            'model' => $config->model,
            'prompt_length' => mb_strlen($prompt),
        ]);

        try {
            // Do not apply OpenAI strict mode: the mapping_json property uses
            // allowAdditionalProperties: true (dynamic keys) which is incompatible
            // with strict mode and causes OpenAI to reject the schema.
            $response = Prism::structured()
                ->using($config->provider, $config->model)
                ->usingProviderConfig(['api_key' => $config->api_key])
                ->withSchema($this->buildSuggestionSchema())
                ->withPrompt($prompt)
                ->asStructured();

            $structured = $response->structured;

            Log::info('AiImportProfileSuggestionService: received response from AI provider', [
                'provider' => $config->provider,
                'model' => $config->model,
                'structured_type' => gettype($structured),
                'structured_keys' => is_array($structured) ? array_keys($structured) : null,
            ]);

            if (! is_array($structured)) {
                Log::error('AiImportProfileSuggestionService: unexpected response structure', [
                    'structured' => $structured,
                ]);
                throw new RuntimeException('AI provider returned an unexpected response structure.');
            }

            return $structured;
        } catch (Exception $e) {
            Log::error('AiImportProfileSuggestionService: AI provider call failed', [
                'provider' => $config->provider,
                'model' => $config->model,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \App\Exceptions\AiProviderFailureException(
                step: 'import_profile_suggestion',
                provider: $config->provider,
                model: $config->model,
                timeout: $this->isTimeoutException($e),
                message: 'AI provider error during import profile suggestion: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Convert the AI response's column_mappings array to mapping_json, stripping unrecognised
     * canonical field names and recording them as confidence notes.
     *
     * The schema uses an array of {header, field} objects (not a dynamic-key object) because
     * OpenAI structured output requires additionalProperties: false on every object schema.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function sanitizeResponse(array $response): array
    {
        $validValues = ImportCanonicalField::values();
        $columnMappings = is_array($response['column_mappings'] ?? null) ? $response['column_mappings'] : [];
        $confidenceNotes = is_array($response['confidence_notes'] ?? null) ? $response['confidence_notes'] : [];

        $sanitizedMapping = [];
        foreach ($columnMappings as $item) {
            if (! is_array($item)) {
                continue;
            }

            $header = isset($item['header']) ? (string) $item['header'] : '';
            $fieldName = isset($item['field']) ? (string) $item['field'] : '';

            if ($header === '') {
                continue;
            }

            if (in_array($fieldName, $validValues, true)) {
                $sanitizedMapping[$header] = $fieldName;
            } else {
                $confidenceNotes[] = [
                    'field' => $header,
                    'note' => sprintf(
                        'AI suggested unknown canonical field name "%s" for column "%s". This mapping was removed.',
                        $fieldName,
                        $header,
                    ),
                ];
            }
        }

        $result = array_merge($response, [
            'mapping_json' => $sanitizedMapping,
            'confidence_notes' => $confidenceNotes,
        ]);

        unset($result['column_mappings']);

        return $result;
    }

    private function buildPrompt(string $trimmedCsv, ?int $accountId): string
    {
        $canonicalFieldDescriptions = implode("\n", [
            '- "date": Transaction date column',
            '- "amount": Transaction amount (positive or negative)',
            '- "payee": Counterparty or merchant name',
            '- "comment": Free-text memo or description',
            '- "reference": Bank reference number or transaction ID (appended to comment)',
            '- "category": Bank-assigned category label (advisory only)',
            '- "ignore": Column present in source file but not imported',
        ]);

        $accountContext = '';
        if ($accountId !== null) {
            $account = AccountEntity::query()->find($accountId);
            if ($account instanceof AccountEntity) {
                $accountContext = sprintf(
                    "\n\nTarget account context:\n- Account name: %s",
                    $account->name,
                );
            }
        }

        return <<<PROMPT
You are analyzing a CSV bank export file to suggest an import profile configuration.

The valid canonical field names for column mapping are:
{$canonicalFieldDescriptions}

Rules:
- Every column header must be mapped to exactly one canonical field name from the list above.
- Use "ignore" for columns that do not represent transaction data.
- Only one column should be mapped to "date", "amount", "payee", or "category".
- Multiple columns may map to "comment" or "reference".
- For "date", detect the PHP date format string (e.g., "d/m/Y", "Y.m.d.", "d.m.Y").
- For amounts, detect whether the decimal separator is "." or ",", and the thousand separator.
- For "sign_handling": use "as_is" when negative amounts represent withdrawals; use "inverted" when positive amounts represent withdrawals.
- Provide a confidence_notes entry for any non-obvious mapping decision, explaining your reasoning.{$accountContext}

CSV sample (first up to 10 data rows):
{$trimmedCsv}

Respond with the structured profile suggestion.
PROMPT;
    }

    private function buildSuggestionSchema(): ObjectSchema
    {
        $canonicalValues = ImportCanonicalField::values();

        return new ObjectSchema(
            name: 'import_profile_suggestion',
            description: 'Suggested import profile configuration derived from the CSV sample.',
            properties: [
                new EnumSchema(
                    name: 'delimiter',
                    description: 'Column delimiter character detected in the CSV.',
                    options: [',', ';', "\t", '|'],
                ),
                new BooleanSchema(
                    name: 'has_header_row',
                    description: 'Whether the first row contains column headers.',
                ),
                new StringSchema(
                    name: 'date_format',
                    description: 'PHP date format string for the date column (e.g., "d/m/Y", "Y.m.d.").',
                    nullable: true,
                ),
                new EnumSchema(
                    name: 'decimal_separator',
                    description: 'Decimal separator used in amount columns.',
                    options: ['.', ','],
                ),
                new EnumSchema(
                    name: 'thousand_separator',
                    description: 'Thousand separator used in amount columns. Use empty string if none.',
                    options: [' ', '.', ',', ''],
                ),
                new EnumSchema(
                    name: 'sign_handling',
                    description: 'How to interpret the sign of amounts.',
                    options: ['as_is', 'inverted'],
                ),
                new ArraySchema(
                    name: 'column_mappings',
                    description: 'Mapping of each CSV column header to a canonical field name. Every column in the sample must have an entry.',
                    items: new ObjectSchema(
                        name: 'column_mapping',
                        description: 'Mapping for a single CSV column.',
                        properties: [
                            new StringSchema('header', 'The CSV column header name exactly as it appears in the file.'),
                            new EnumSchema('field', 'The canonical field name. Use "ignore" for columns not needed.', $canonicalValues),
                        ],
                        requiredFields: ['header', 'field'],
                    ),
                ),
                new ArraySchema(
                    name: 'confidence_notes',
                    description: 'Confidence notes explaining non-obvious mapping decisions.',
                    items: new ObjectSchema(
                        name: 'confidence_note',
                        description: 'Note for a single field or mapping decision.',
                        properties: [
                            new StringSchema('field', 'The field or column header this note refers to.'),
                            new StringSchema('note', 'Explanation of the reasoning or confidence level.'),
                        ],
                        requiredFields: ['field', 'note'],
                    ),
                ),
            ],
            requiredFields: [
                'delimiter',
                'has_header_row',
                'date_format',
                'decimal_separator',
                'thousand_separator',
                'sign_handling',
                'column_mappings',
                'confidence_notes',
            ],
        );
    }

    /**
     * Convert file content to UTF-8 using the same fallback chain as CsvParserService.
     * Non-UTF-8 bytes in the prompt would cause JSON encoding failures when calling the AI provider.
     */
    private function normalizeToUtf8(string $content): string
    {
        if ($content === '' || mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $supportedEncodings = ['UTF-8', 'Windows-1252', 'ISO-8859-2', 'ISO-8859-1'];
        $detectedEncoding = mb_detect_encoding($content, $supportedEncodings, true);

        if (is_string($detectedEncoding) && $detectedEncoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        foreach (['Windows-1252', 'ISO-8859-2', 'ISO-8859-1'] as $fallbackEncoding) {
            $converted = mb_convert_encoding($content, 'UTF-8', $fallbackEncoding);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    }

    private function isTimeoutException(Exception $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'timeout') || str_contains($message, 'timed out');
    }
}
