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

class TransactionCreatedFromEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public ReceivedMail $mail;
    public array $transaction;
    public User $user;

    /**
     * Create a new message instance.
     *
     */
    public function __construct(ReceivedMail $mail)
    {
        $this->mail = $mail;
        $this->transaction = $mail->transaction_data;
        $this->user = $mail->user;
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('yaffa.incoming_receipts_email'),
                config('app.name')
            ),
            subject: 'Transaction Created From Your Email',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.transaction-created-from-email',
            with: [
                'transaction' => $this->transaction,
                'user' => $this->user,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments(): array
    {
        return [];
    }
}
