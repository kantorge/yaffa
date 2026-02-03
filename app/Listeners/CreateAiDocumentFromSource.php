<?php

namespace App\Listeners;

use App\Events\DocumentImported;
use App\Events\EmailReceived;
use App\Models\AiDocumentFile;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Log;

class CreateAiDocumentFromSource implements ShouldQueue
{
    public function __construct()
    {
    }

    /**
     * Handle EmailReceived event
     */
    public function handleEmailReceived(EmailReceived $event): void
    {
        try {
            $receivedMail = $event->receivedMail;
            $user = $receivedMail->user;

            // Create AI document
            $document = $user->aiDocuments()->create([
                'source_type' => 'received_email',
                'status' => 'ready_for_processing',
                'received_mail_id' => $receivedMail->id,
            ]);

            // Store email content as text file
            $emailContent = $this->formatEmailContent($receivedMail);
            $filename = 'email_' . now()->timestamp . '.txt';

            $path = "ai_documents/{$user->id}/{$document->id}/{$filename}";

            Storage::disk('local')->put($path, $emailContent);

            AiDocumentFile::create([
                'ai_document_id' => $document->id,
                'file_path' => $path,
                'file_name' => $filename,
                'file_type' => 'txt',
            ]);

            Log::info("Created AI document {$document->id} from received email {$receivedMail->id}");
        } catch (Exception $e) {
            Log::error("Failed to create AI document from email: {$e->getMessage()}");
        }
    }

    /**
     * Handle DocumentImported event
     */
    public function handleDocumentImported(DocumentImported $event): void
    {
        try {
            $importedDocument = $event->aiDocument;

            // Document already created with AI document status
            Log::info("AI document {$importedDocument->id} created from Google Drive import");
        } catch (Exception $e) {
            Log::error("Failed to handle document imported event: {$e->getMessage()}");
        }
    }

    /**
     * Format email content for AI processing
     */
    private function formatEmailContent($receivedMail): string
    {
        $parts = [];

        $parts[] = "Subject: {$receivedMail->subject}";
        $from = $receivedMail->from ?? 'Unknown';
        $parts[] = "From: {$from}";
        $parts[] = "Date: {$receivedMail->created_at->format('Y-m-d H:i:s')}";
        $parts[] = '';
        $parts[] = '---';
        $parts[] = '';

        // Prefer HTML over text, but clean it up
        if ($receivedMail->html) {
            $content = $this->cleanHtmlContent($receivedMail->html);
        } else {
            $content = $receivedMail->text ?? '';
        }

        $parts[] = $content;

        return implode("\n", $parts);
    }

    /**
     * Clean HTML content for processing
     */
    private function cleanHtmlContent(string $html): string
    {
        // Remove style attributes and style tags
        $html = preg_replace('/\s*style\s*=\s*["\'].*?["\']/i', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Remove script tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);

        // Remove SVG elements
        $html = preg_replace('/<svg[^>]*>.*?<\/svg>/is', '', $html);

        // Remove base64 data URIs (images, etc.)
        $html = preg_replace('/data:image\/[^;]*;base64,[A-Za-z0-9+\/=]+/i', '', $html);

        // Remove img tags but keep src references as text
        $html = preg_replace('/<img[^>]*src=["\'](.*?)["\'][^>]*>/i', 'Image: $1', $html);

        // Convert common HTML entities to plain text
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        // Remove remaining HTML tags but keep content
        $html = strip_tags($html);

        // Clean up multiple spaces and newlines
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }

    /**
     * Register event listeners
     */
    public function subscribe($events)
    {
        $events->listen(
            EmailReceived::class,
            [self::class, 'handleEmailReceived']
        );

        $events->listen(
            DocumentImported::class,
            [self::class, 'handleDocumentImported']
        );
    }
}
