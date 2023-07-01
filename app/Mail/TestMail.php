<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestMail extends Mailable
{
    public string $sender;
    public $subject;
    public string $body;

    public function __construct($sender, $subject, $body)
    {
        $this->sender = $sender;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function build(): TestMail
    {
        return $this
            ->from($this->sender)
            ->subject($this->subject)
            ->markdown('emails.testmail');
    }
}
