<?php

namespace App\Events;

use App\Models\ReceivedMail;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingEmailReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public ReceivedMail $mail;

    /**
     * Create a new event instance.
     *
     */
    public function __construct(ReceivedMail $mail)
    {
        $this->mail = $mail;
    }
}
