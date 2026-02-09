<?php

namespace App\Mail;

use App\Models\AiDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiDocumentProcessed extends Mailable
{
    use Queueable;
    use SerializesModels;

    public AiDocument $document;

    public function __construct(AiDocument $document)
    {
        $this->document = $document;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: __('Document Processed - Ready for Review'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ai-document-processed',
            with: [
                'document' => $this->document,
                'draftData' => $this->document->processed_transaction_data ?? [],
            ],
        );
    }
}
