<?php

namespace App\Mail;

use App\Models\AiDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiDocumentDismissedNonFinancial extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public AiDocument $document)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: __('mail.ai_document_dismissed_non_financial.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ai-document-dismissed-non-financial',
            with: ['document' => $this->document],
        );
    }
}
