<?php

namespace App\Notifications;

use App\Models\AiDocument;
use Illuminate\Notifications\Notification;

/**
 * Tells the user's app that an AI document changed state. The payload deliberately holds only a type,
 * the entity id and a generic title: no amounts, payees or account names, so it is safe to push later.
 */
class AiDocumentStatusNotification extends Notification
{
    public const string READY_FOR_REVIEW = 'ai_document.ready_for_review';
    public const string PROCESSING_FAILED = 'ai_document.processing_failed';

    public function __construct(private readonly AiDocument $document, private readonly string $type)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, entity_type: string, entity_id: int, title: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'entity_type' => 'ai_document',
            'entity_id' => $this->document->id,
            'title' => $this->type === self::PROCESSING_FAILED
                ? __('A document could not be processed')
                : __('A document is ready for review'),
        ];
    }
}
