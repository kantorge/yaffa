<?php

namespace Tests\Unit\Services\Import;

use App\Models\AiDocument;
use App\Models\User;
use App\Services\Import\ImportNormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportNormalizationServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame('Food', $drafts[0]['source_category']);

        $this->assertSame('pending_review', $drafts[1]['status']);
        $this->assertNull($drafts[1]['source_category']);
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

    public function test_enrichs_drafts_with_related_ai_document_candidates_using_matching_signals(): void
    {
        $service = new ImportNormalizationService();
        $user = User::factory()->create();

        $bestMatch = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'date' => '2025-01-05',
                'payee' => 'Grocery Store',
                'config' => [
                    'amount_from' => 123.45,
                    'amount_to' => 123.45,
                ],
            ],
        ]);

        $secondaryMatch = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_at' => now()->subDay(),
            'processed_transaction_data' => [
                'date' => '2025-01-06',
                'merchant' => 'Grocery',
                'amount' => 123.4,
            ],
        ]);

        AiDocument::factory()->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'date' => '2025-01-05',
                'payee' => 'Grocery Store',
                'amount' => 123.45,
            ],
        ]);

        $drafts = $service->enrichDraftsWithRelatedAiDocuments($user, [[
            'date' => '2025-01-05',
            'amount' => 123.45,
            'payee' => 'Grocery Store',
            'related_ai_documents' => [],
        ]]);

        $this->assertCount(2, $drafts[0]['related_ai_documents']);
        $this->assertSame($bestMatch->id, $drafts[0]['related_ai_documents'][0]['ai_document_id']);
        $this->assertSame(['amount', 'date', 'payee'], $drafts[0]['related_ai_documents'][0]['matched_on']);
        $this->assertGreaterThan(
            $drafts[0]['related_ai_documents'][1]['confidence_score'],
            $drafts[0]['related_ai_documents'][0]['confidence_score'],
        );
        $this->assertSame($secondaryMatch->id, $drafts[0]['related_ai_documents'][1]['ai_document_id']);
    }

    public function test_related_ai_document_matching_is_bounded_by_time_window_and_result_count(): void
    {
        $service = new ImportNormalizationService();
        $user = User::factory()->create();

        $oldDocument = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'created_at' => now()->subDays(90),
            'processed_at' => now()->subDays(90),
            'processed_transaction_data' => [
                'date' => '2024-10-01',
                'payee' => 'Coffee Shop',
                'amount' => 12.5,
            ],
        ]);

        for ($index = 0; $index < 6; $index++) {
            AiDocument::factory()->for($user)->create([
                'status' => 'ready_for_review',
                'created_at' => now()->subDays($index),
                'processed_at' => now()->subDays($index),
                'processed_transaction_data' => [
                    'date' => '2025-01-05',
                    'payee' => 'Coffee Shop',
                    'amount' => 12.5,
                ],
            ]);
        }

        $drafts = $service->enrichDraftsWithRelatedAiDocuments($user, [[
            'date' => '2025-01-05',
            'amount' => 12.5,
            'payee' => 'Coffee Shop',
            'related_ai_documents' => [],
        ]]);

        $this->assertCount(3, $drafts[0]['related_ai_documents']);
        $this->assertNotContains($oldDocument->id, array_column($drafts[0]['related_ai_documents'], 'ai_document_id'));
    }

    public function test_related_ai_document_matching_excludes_a_lone_weak_signal(): void
    {
        $service = new ImportNormalizationService();
        $user = User::factory()->create();

        // Only a loose date match (4-7 days apart): unrelated amount and payee.
        AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'date' => '2025-01-12',
                'payee' => 'Unrelated Merchant',
                'amount' => 480.93,
            ],
        ]);

        $drafts = $service->enrichDraftsWithRelatedAiDocuments($user, [[
            'date' => '2025-01-05',
            'amount' => 9450.00,
            'payee' => 'Card Transaction',
            'related_ai_documents' => [],
        ]]);

        $this->assertSame([], $drafts[0]['related_ai_documents']);
    }

    public function test_related_ai_document_matching_includes_a_lone_strong_amount_signal(): void
    {
        $service = new ImportNormalizationService();
        $user = User::factory()->create();

        // Exact amount match, but unrelated date and payee.
        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'date' => '2025-02-20',
                'payee' => 'Unrelated Merchant',
                'amount' => 9450.00,
            ],
        ]);

        $drafts = $service->enrichDraftsWithRelatedAiDocuments($user, [[
            'date' => '2025-01-05',
            'amount' => 9450.00,
            'payee' => 'Card Transaction',
            'related_ai_documents' => [],
        ]]);

        $this->assertCount(1, $drafts[0]['related_ai_documents']);
        $this->assertSame($document->id, $drafts[0]['related_ai_documents'][0]['ai_document_id']);
        $this->assertSame(['amount'], $drafts[0]['related_ai_documents'][0]['matched_on']);
    }

    public function test_related_ai_document_matching_includes_two_combined_weak_signals(): void
    {
        $service = new ImportNormalizationService();
        $user = User::factory()->create();

        // Loose date match (within 7 days) plus a fuzzy payee match; amount unrelated.
        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'date' => '2025-01-12',
                'payee' => 'Grocery Store',
                'amount' => 1.23,
            ],
        ]);

        $drafts = $service->enrichDraftsWithRelatedAiDocuments($user, [[
            'date' => '2025-01-05',
            'amount' => 9450.00,
            'payee' => 'Grocery Store',
            'related_ai_documents' => [],
        ]]);

        $this->assertCount(1, $drafts[0]['related_ai_documents']);
        $this->assertSame($document->id, $drafts[0]['related_ai_documents'][0]['ai_document_id']);
        $this->assertSame(['date', 'payee'], $drafts[0]['related_ai_documents'][0]['matched_on']);
    }
}
