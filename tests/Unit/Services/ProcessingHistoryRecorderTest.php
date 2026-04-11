<?php

namespace Tests\Unit\Services;

use App\Models\AiDocument;
use App\Models\User;
use App\Services\ProcessingHistoryRecorder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessingHistoryRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_processing_history_stores_entry_with_expected_shape(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();
        $recorder = new ProcessingHistoryRecorder();

        $recorder->appendProcessingHistory(
            $document,
            'main_extraction',
            'Prompt content',
            '{"ok":true}',
            true,
        );

        $document->refresh();

        $this->assertIsArray($document->ai_chat_history);
        $this->assertCount(1, $document->ai_chat_history);
        $this->assertSame('main_extraction', $document->ai_chat_history[0]['step']);
        $this->assertSame('Prompt content', $document->ai_chat_history[0]['prompt']);
        $this->assertSame('{"ok":true}', $document->ai_chat_history[0]['response']);
        $this->assertArrayNotHasKey('include_in_prompt_history', $document->ai_chat_history[0]);
        $this->assertArrayHasKey('timestamp', $document->ai_chat_history[0]);
    }

    public function test_append_ai_fallback_history_after_failure_records_non_prompt_history_entry(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();
        $recorder = new ProcessingHistoryRecorder();

        $recorder->appendAiFallbackHistoryAfterFailure(
            $document,
            'category_batch_matching',
            'Original prompt',
            new Exception('cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received'),
        );

        $document->refresh();

        $this->assertIsArray($document->ai_chat_history);
        $this->assertCount(1, $document->ai_chat_history);
        $this->assertSame('category_batch_matching', $document->ai_chat_history[0]['step']);
        $this->assertFalse($document->ai_chat_history[0]['include_in_prompt_history']);
        $this->assertStringContainsString('fallback (AI call failed)', $document->ai_chat_history[0]['prompt']);
        $this->assertStringContainsString('Operation timed out', $document->ai_chat_history[0]['prompt']);
        $this->assertStringContainsString('- Recommended Category Id: N/A', $document->ai_chat_history[0]['response']);
    }

    public function test_has_ai_failure_fallback_history_detects_existing_fallback_entry(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();
        $recorder = new ProcessingHistoryRecorder();

        $recorder->appendAiFallbackHistoryAfterFailure(
            $document,
            'category_batch_matching',
            'Original prompt',
            new Exception('Request failed'),
        );

        $document->refresh();

        $this->assertTrue($recorder->hasAiFailureFallbackHistory($document, 'category_batch_matching'));
        $this->assertFalse($recorder->hasAiFailureFallbackHistory($document, 'main_extraction'));
    }
}
