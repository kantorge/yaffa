<?php
  function add_notification(	$message,
                              $type = "info",
                              $title = "",
                              $icon = "",
                              $dismissable = false) {
      add_flash_message (
          Array(
              'message'		=> $message,
              'type'			=> $type,
              'title'			=> $title,
              'icon'			=> $icon,
              'dismissable'	=> $dismissable
          )
      );
  }

  function add_flash_message( array $notification){
      session()->flash( 'any_notifications', true );
      if (empty( session( 'notification_collection' ) ))
      {
        // If notification_collection is either not set or not a collection
        $notification_collection = new \Illuminate\Support\Collection();
      } else {
        // Add to the notification-collection
        $notification_collection = \Session::get( 'notification_collection' );
      }

      $notification_collection->push([
          'title' => $notification['title'],
          'message' => $notification['message'],
          'type' => $notification['type'],
          'icon' => $notification['icon'],
          'dismissable' => $notification['dismissable'],
      ]);
      session()->flash( 'notification_collection', $notification_collection );
    }