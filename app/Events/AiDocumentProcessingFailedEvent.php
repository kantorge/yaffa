<?php

namespace App\Events;

use App\Models\AiDocument;
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
        public string $errorMessage,
        public string $exceptionClass,
        public int $errorCode
    ) {
    }
}
