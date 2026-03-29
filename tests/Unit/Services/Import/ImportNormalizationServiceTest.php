<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\ImportNormalizationService;
use Tests\TestCase;

class ImportNormalizationServiceTest extends TestCase
{
    public function test_normalizes_qif_entries_into_draft_payloads(): void
    {
        $service = new ImportNormalizationService();

        $entries = [
            [
                'date_raw' => '2025-01-05',
                'amount_raw' => '-1 234,56',
                'payee' => 'Grocery Store',
                'memo' => 'Weekly shopping',
                'category' => 'Food',
                'reference' => 'INV-42',
                'raw_entry' => 'D2025-01-05\nT-1 234,56\nPGrocery Store',
                'warnings' => [],
            ],
            [
                'date_raw' => '01/02/2025',
                'amount_raw' => '250.75',
                'payee' => 'Salary',
                'memo' => null,
                'category' => null,
                'reference' => null,
                'raw_entry' => 'D01/02/2025\nT250.75\nPSalary',
                'warnings' => ['Split transaction detail was detected and kept in raw_entry, but split lines were not imported.'],
            ],
        ];

        $drafts = $service->normalizeQifEntries($entries, 99);

        $this->assertCount(2, $drafts);

        $this->assertSame('pending_review', $drafts[0]['status']);
        $this->assertSame('2025-01-05', $drafts[0]['date']);
        $this->assertSame(1234.56, $drafts[0]['amount']);
        $this->assertSame('withdrawal', $drafts[0]['transaction_type']);
        $this->assertSame(99, $drafts[0]['config']['account_from_id']);
        $this->assertSame(1234.56, $drafts[0]['config']['amount_from']);

        $this->assertSame('pending_review', $drafts[1]['status']);
        $this->assertSame('2025-02-01', $drafts[1]['date']);
        $this->assertSame(250.75, $drafts[1]['amount']);
        $this->assertSame('deposit', $drafts[1]['transaction_type']);
        $this->assertSame(99, $drafts[1]['config']['account_to_id']);
        $this->assertContains(
            'Ambiguous date format "01/02/2025" was parsed using day/month interpretation.',
            $drafts[1]['warnings'],
        );
    }

    public function test_marks_invalid_entry_as_failed_validation_and_keeps_warnings(): void
    {
        $service = new ImportNormalizationService();

        $drafts = $service->normalizeQifEntries([
            [
                'date_raw' => '2025/99/10',
                'amount_raw' => 'N/A',
                'payee' => 'Broken Entry',
                'memo' => null,
                'category' => null,
                'reference' => null,
                'raw_entry' => 'broken',
                'warnings' => ['Unsupported QIF marker line "Xunknown" was kept in raw_entry.'],
            ],
        ], 12);

        $this->assertSame('failed_validation', $drafts[0]['status']);
        $this->assertNull($drafts[0]['date']);
        $this->assertNull($drafts[0]['amount']);
        $this->assertContains('Invalid date format "2025/99/10".', $drafts[0]['warnings']);
        $this->assertContains('Invalid amount format "N/A".', $drafts[0]['warnings']);
        $this->assertContains(
            'Unsupported QIF marker line "Xunknown" was kept in raw_entry.',
            $drafts[0]['warnings'],
        );
    }
}
