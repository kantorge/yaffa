<?php

namespace App\Events;

use App\Models\AiDocument;
use Exception;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiDocumentProcessingFailedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public AiDocument $document,
        public Exception $exception
    ) {
    }
}
