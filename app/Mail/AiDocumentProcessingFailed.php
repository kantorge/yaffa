<?php

namespace App\Mail;

use App\Models\AiDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiDocumentProcessingFailed extends Mailable
{
    use Queueable;
    use SerializesModels;

    public AiDocument $document;

    public string $errorMessage;

    public string $exceptionClass;

    public int $errorCode;

    public function __construct(AiDocument $document, string $errorMessage, string $exceptionClass, int $errorCode)
    {
        $this->document = $document;
        $this->errorMessage = $errorMessage;
        $this->exceptionClass = $exceptionClass;
        $this->errorCode = $errorCode;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: __('mail.ai_document_processing_failed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ai-document-processing-failed',
            with: [
                'document' => $this->document,
                'error' => $this->errorMessage,
            ],
        );
    }
}
