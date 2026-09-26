<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Reminder about old AI documents which are not finalized yet, so the retention cleanup keeps them.
 */
class AiDocumentsAwaitingAction extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public int $count,
        public int $retentionDays,
        public Carbon $cutoff,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: __('mail.ai_documents_awaiting_action.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ai-documents-awaiting-action',
            with: [
                'user' => $this->user,
                'count' => $this->count,
                'retentionDays' => $this->retentionDays,
                'url' => route('ai-documents.index', [
                    'status' => 'unprocessed',
                    'date_to' => $this->cutoff->toDateString(),
                ]),
            ],
        );
    }
}
