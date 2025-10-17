<?php

namespace App\Mail;

use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionErrorFromEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public ReceivedMail $mail;
    public User $user;
    public string $error;

    /**
     * Create a new message instance.
     *
     */
    public function __construct(ReceivedMail $mail, string $error)
    {
        $this->mail = $mail;
        $this->user = $mail->user;
        $this->error = $error;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('yaffa.incoming_receipts_email'),
                config('app.name')
            ),
            subject: 'Error While Processing Transaction From Your Email',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.transaction-error-from-email',
            with: [
                'mail' => $this->mail,
                'user' => $this->user,
                'error' => $this->error,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
