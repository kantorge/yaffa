<?php

namespace App\Mail;

use App\Models\AiDocument;
use Exception;
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

    public Exception $exception;

    public function __construct(AiDocument $document, Exception $exception)
    {
        $this->document = $document;
        $this->exception = $exception;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: __('Document Processing Failed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ai-document-processing-failed',
            with: [
                'document' => $this->document,
                'error' => $this->exception->getMessage(),
            ],
        );
    }
}
