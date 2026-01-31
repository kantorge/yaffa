<?php

namespace App\Events;

use App\Models\AiDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentImported
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AiDocument $aiDocument) {}
}
