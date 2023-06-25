<?php

namespace App\Components;

use App\Events\IncomingEmailReceived;
use App\Models\ReceivedMail;
use App\Models\User;
use BeyondCode\Mailbox\InboundEmail;

class MailHandler
{
    public function __invoke(InboundEmail $email): void
    {
        // Ignore emails sent by non-existing users (verification is not required)
        $user = User::where('email', $email->from())->first();
        if (!$user) {
            return;
        }

        // Store the email in the database
        $receivedMail = ReceivedMail::create([
            'message_id' => $email->id(),
            'user_id' => $user->id,
            'subject' => $email->subject(),
            'html' => $email->html(),
            'text' => $email->text(),
        ]);

        // Generate an event to process the email
        event(new IncomingEmailReceived($receivedMail));
    }
}
