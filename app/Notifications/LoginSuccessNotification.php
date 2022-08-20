<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginSuccessNotification extends Notification
{
    protected $event;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($event)
    {
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail()
    {
        logger()->debug('event', ['event' => $this->event]);

        return (new MailMessage())
            ->subject('YAFFA info - successful login')
            ->line('Successful login at '.config('app.url').' for user '.$this->event->user->email);
    }
}
