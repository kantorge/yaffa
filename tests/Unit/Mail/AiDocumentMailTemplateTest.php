<?php

namespace Tests\Unit\Mail;

use App\Mail\AiDocumentProcessed;
use App\Mail\AiDocumentProcessingFailed;
use App\Models\AiDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use RuntimeException;

class AiDocumentMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_processed_email_includes_review_and_settings_links(): void
    {
        /** @var AiDocument $document */
        $document = AiDocument::factory()->create([
            'status' => 'ready_for_review',
            'source_type' => 'manual_upload',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'date' => '2026-02-25',
                'raw' => [
                    'transaction_type' => 'withdrawal',
                    'amount' => '12.50',
                    'currency' => 'USD',
                    'payee' => 'Coffee Shop',
                ],
            ],
        ]);

        $mailable = new AiDocumentProcessed($document);

        $mailable->assertSeeInHtml('Your AI document is ready for review.');
        $mailable->assertSeeInHtml('Review Document');
        $mailable->assertSeeInHtml('Open AI Documents');
        $mailable->assertSeeInHtml('AI Settings');
        $mailable->assertSeeInHtml(route('ai-documents.show', $document->id));
        $mailable->assertSeeInHtml(route('ai-documents.index'));
        $mailable->assertSeeInHtml(route('user.settings'));
    }

    public function test_failed_email_includes_reason_and_recovery_links(): void
    {
        /** @var AiDocument $document */
        $document = AiDocument::factory()->create([
            'status' => 'processing_failed',
            'source_type' => 'received_email',
        ]);

        $mailable = new AiDocumentProcessingFailed(
            $document,
            'No OCR method available for image-only document.',
            RuntimeException::class,
            0,
        );

        $mailable->assertSeeInHtml('Your AI document could not be processed.');
        $mailable->assertSeeInHtml('No OCR method available for image-only document.');
        $mailable->assertSeeInHtml('Review');
        $mailable->assertSeeInHtml('Reprocess');
        $mailable->assertSeeInHtml('Open AI Settings');
        $mailable->assertSeeInHtml(route('ai-documents.show', $document->id));
        $mailable->assertSeeInHtml(route('user.settings'));
    }
}
