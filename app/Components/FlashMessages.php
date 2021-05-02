<?php

namespace App\Components;

use Illuminate\Support\Collection;

trait FlashMessages
{

    public static function addMessage($message, $type = "info", $title = "", $icon = "", $dismissable = false) :void
    {
        $existingData = session()->get('notification_collection');

        if (empty($existingData)) {
            // If notification_collection is either not set or not a collection, initialize it
            $existingData = new Collection;
        }

        //add new item
        $existingData->push([
            'message' => $message,
            'title' => $title,
            'type' => $type,
            'icon' => $icon,
            'dismissable' => $dismissable,
        ]);

        session()->flash('notification_collection', $existingData);
    }

    public static function getMessages()
    {
        return self::hasMessages() ? session()->get('notification_collection') : new Collection;
    }

    public static function hasMessages()
    {
        return session()->has('notification_collection');
    }

    public static function addSimpleSuccessMessage($message)
    {
        self::addMessage($message,'success');
    }

    public static function addSimpleInfoMessage($message)
    {
        self::addMessage($message,'info');
    }

    public static function addSimpleWarningMessage($message)
    {
        self::addMessage($message,'warning');
    }

    public static function addSimpleDangerMessage($message)
    {
        self::addMessage($message,'danger');
    }
}
