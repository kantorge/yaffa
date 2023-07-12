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

        // Create and store the email in the database
        $receivedMail = new ReceivedMail([
            'message_id' => $email->id(),
            'user_id' => $user->id,
            'subject' => $email->subject(),
            'html' => $email->html(),
            'text' => $email->text(),
        ]);

        // Ensure that subject is available
        if (!$receivedMail->subject) {
            $receivedMail->subject = __('(No subject)');
        }

        // Ensure that the HTML version is not null
        if (!$receivedMail->html) {
            $receivedMail->html = '';
        }

        // If text version is not available, strip the content of the HTML version
        if (!$receivedMail->text) {
            $receivedMail->text = strip_tags($receivedMail->html);
        }

        $receivedMail->save();

        // Generate an event to process the email
        event(new IncomingEmailReceived($receivedMail));
    }
}
