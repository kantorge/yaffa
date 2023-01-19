<?php

namespace App\Components;

use Illuminate\Support\Collection;

trait FlashMessages
{
    public static function addMessage($message, $type = 'info', $title = '', $icon = '', $dismissible = false): void
    {
        if (session()->has('notification_collection')) {
            $existingData = session()->get('notification_collection');
        } else {
            $existingData = new Collection();
        }

        // Add new item
        $existingData->push([
            'message' => $message,
            'title' => $title,
            'type' => $type,
            'icon' => $icon,
            'dismissible' => $dismissible,
        ]);

        session()->flash('notification_collection', $existingData);
    }

    public static function getMessages()
    {
        return self::hasMessages() ? session()->get('notification_collection') : new Collection();
    }

    public static function hasMessages(): bool
    {
        return session()->has('notification_collection');
    }

    public static function addSimpleSuccessMessage($message): void
    {
        self::addMessage($message, 'success');
    }

    public static function addSimpleInfoMessage($message): void
    {
        self::addMessage($message, 'info');
    }

    public static function addSimpleWarningMessage($message): void
    {
        self::addMessage($message, 'warning');
    }

    public static function addSimpleDangerMessage($message): void
    {
        self::addMessage($message, 'danger');
    }
}
