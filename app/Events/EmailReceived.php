<?php

namespace App\Events;

use App\Models\ReceivedMail;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ReceivedMail $receivedMail) {}
}
