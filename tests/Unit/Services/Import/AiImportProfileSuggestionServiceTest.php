<?php

namespace Tests\Unit\Services\Import;

use App\Enums\ImportCanonicalField;
use App\Services\Import\AiImportProfileSuggestionService;
use RuntimeException;
use Tests\TestCase;
use ReflectionClass;

class AiImportProfileSuggestionServiceTest extends TestCase
{
    private AiImportProfileSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiImportProfileSuggestionService();
    }

    public function test_trim_csv_returns_header_row_and_up_to_ten_data_rows(): void
    {
        $rows = ["Date,Amount,Payee"];
        for ($i = 1; $i <= 15; $i++) {
            $rows[] = "2025-01-{$i},100.00,Store {$i}";
        }
        $csvContent = implode("\n", $rows);

        [$trimmedCsv, $headers] = $this->service->trimCsvToSampleRows($csvContent);

        $this->assertSame(['Date', 'Amount', 'Payee'], $headers);

        $lines = array_filter(explode("\n", mb_trim($trimmedCsv)));
        // First line is header, then at most 10 data rows
        $this->assertCount(11, $lines);
    }

    public function test_trim_csv_with_fewer_than_ten_rows_returns_all(): void
    {
        $csvContent = "Date,Amount\n2025-01-01,100.00\n2025-01-02,200.00";

        [$trimmedCsv, $headers] = $this->service->trimCsvToSampleRows($csvContent);

        $this->assertSame(['Date', 'Amount'], $headers);

        $lines = array_filter(explode("\n", mb_trim($trimmedCsv)));
        $this->assertCount(3, $lines); // header + 2 data rows
    }

    public function test_trim_csv_throws_for_empty_content(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be parsed as a CSV file');

        $this->service->trimCsvToSampleRows('');
    }

    public function test_sanitize_response_strips_unknown_canonical_field_names(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeResponse');
        $method->setAccessible(true);

        $response = [
            'delimiter' => ',',
            'has_header_row' => true,
            'date_format' => 'd/m/Y',
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'sign_handling' => 'as_is',
            'column_mappings' => [
                ['header' => 'Date', 'field' => 'date'],
                ['header' => 'Amount', 'field' => 'amount'],
                ['header' => 'Description', 'field' => 'comment'],
                ['header' => 'Ref', 'field' => 'made_up_field'], // unknown
            ],
            'confidence_notes' => [],
        ];

        $result = $method->invoke($this->service, $response);

        $this->assertArrayHasKey('Date', $result['mapping_json']);
        $this->assertArrayHasKey('Amount', $result['mapping_json']);
        $this->assertArrayHasKey('Description', $result['mapping_json']);
        $this->assertArrayNotHasKey('Ref', $result['mapping_json']);
        $this->assertArrayNotHasKey('column_mappings', $result);

        $noteFields = array_column($result['confidence_notes'], 'field');
        $this->assertContains('Ref', $noteFields);

        $strippedNote = collect($result['confidence_notes'])->firstWhere('field', 'Ref');
        $this->assertStringContainsString('made_up_field', $strippedNote['note']);
    }

    public function test_sanitize_response_keeps_all_valid_canonical_field_names(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeResponse');
        $method->setAccessible(true);

        $columnMappings = [];
        foreach (ImportCanonicalField::values() as $fieldName) {
            $columnMappings[] = ['header' => "Col_{$fieldName}", 'field' => $fieldName];
        }

        $response = [
            'delimiter' => ',',
            'has_header_row' => true,
            'date_format' => 'Y-m-d',
            'decimal_separator' => '.',
            'thousand_separator' => '',
            'sign_handling' => 'as_is',
            'column_mappings' => $columnMappings,
            'confidence_notes' => [],
        ];

        $result = $method->invoke($this->service, $response);

        foreach (ImportCanonicalField::values() as $fieldName) {
            $this->assertArrayHasKey("Col_{$fieldName}", $result['mapping_json']);
        }

        $this->assertEmpty($result['confidence_notes']);
        $this->assertArrayNotHasKey('column_mappings', $result);
    }

    public function test_sanitize_response_preserves_existing_confidence_notes(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeResponse');
        $method->setAccessible(true);

        $response = [
            'delimiter' => ',',
            'has_header_row' => true,
            'date_format' => null,
            'decimal_separator' => ',',
            'thousand_separator' => '.',
            'sign_handling' => 'inverted',
            'column_mappings' => [
                ['header' => 'Date', 'field' => 'date'],
                ['header' => 'Amt', 'field' => 'amount'],
            ],
            'confidence_notes' => [
                ['field' => 'date_format', 'note' => 'Could not detect date format from sample values.'],
            ],
        ];

        $result = $method->invoke($this->service, $response);

        $this->assertCount(1, $result['confidence_notes']);
        $this->assertSame('date_format', $result['confidence_notes'][0]['field']);
    }

    public function test_trim_csv_properly_escapes_quoted_fields(): void
    {
        $csvContent = "Date,Amount,Description\n2025-01-01,100.00,\"Grocery, Store\"\n2025-01-02,200.00,Salary";

        [$trimmedCsv, $headers] = $this->service->trimCsvToSampleRows($csvContent);

        $this->assertSame(['Date', 'Amount', 'Description'], $headers);
        $this->assertStringContainsString('Grocery', $trimmedCsv);
    }
}
