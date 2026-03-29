<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\QifParserService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QifParserServiceTest extends TestCase
{
    public function test_parses_supported_qif_entries_and_split_warning(): void
    {
        $content = <<<'QIF'
!Type:Bank
D2025-01-05
T-1 234,56
PGrocery Store
MWeekly shopping
LFood
NINV-42
^
D01/02/2025
T250.75
PSalary
SCategory A
EPart A
$125.00
^
QIF;

        $service = new QifParserService();
        $parsed = $service->parseContent($content);

        $this->assertCount(2, $parsed['entries']);
        $this->assertSame('2025-01-05', $parsed['entries'][0]['date_raw']);
        $this->assertSame('-1 234,56', $parsed['entries'][0]['amount_raw']);
        $this->assertSame('Grocery Store', $parsed['entries'][0]['payee']);
        $this->assertSame('INV-42', $parsed['entries'][0]['reference']);
        $this->assertSame('01/02/2025', $parsed['entries'][1]['date_raw']);
        $this->assertContains(
            'Split transaction detail was detected and kept in raw_entry, but split lines were not imported.',
            $parsed['entries'][1]['warnings'],
        );
    }

    public function test_skips_unsupported_sections_with_non_blocking_warning(): void
    {
        $content = <<<'QIF'
!Type:Invst
D2025-01-10
T100.00
PShould be skipped
^
!Type:Cash
D2025-01-11
T-20.00
PIncluded entry
^
QIF;

        $service = new QifParserService();
        $parsed = $service->parseContent($content);

        $this->assertCount(1, $parsed['entries']);
        $this->assertSame('Included entry', $parsed['entries'][0]['payee']);
        $this->assertNotEmpty($parsed['warnings']);
        $this->assertStringContainsString('Unsupported QIF section type "Invst"', $parsed['warnings'][0]);
    }

    public function test_imports_last_entry_when_terminator_is_missing(): void
    {
        $content = <<<'QIF'
!Type:Bank
D2025-03-01
T-50.00
PNo terminator entry
QIF;

        $service = new QifParserService();
        $parsed = $service->parseContent($content);

        $this->assertCount(1, $parsed['entries']);
        $this->assertSame('No terminator entry', $parsed['entries'][0]['payee']);
        $this->assertContains(
            'The last QIF entry was missing the terminator (^) and was imported at end of file.',
            $parsed['entries'][0]['warnings'],
        );
    }

    public function test_parse_file_converts_non_utf8_content_before_parsing(): void
    {
        $iso88591Payee = utf8_decode('Déjà vu payee');
        $content = "!Type:Bank\nD2025-03-01\nT-50.00\nP{$iso88591Payee}\n^\n";

        $file = UploadedFile::fake()->createWithContent('import.qif', $content);

        $service = new QifParserService();
        $parsed = $service->parseFile($file);

        $this->assertCount(1, $parsed['entries']);
        $this->assertSame('Déjà vu payee', $parsed['entries'][0]['payee']);
        $this->assertNotEmpty($parsed['warnings']);
        $this->assertStringContainsString('converted to UTF-8', implode(' ', $parsed['warnings']));
    }
}
