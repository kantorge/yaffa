<?php

namespace App\Notifications;

use Illuminate\Auth\Events\Failed;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginFailedNotification extends Notification
{
    protected Failed $event;

    /**
     * Create a new notification instance.
     */
    public function __construct($event)
    {
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->subject('YAFFA alert - failed login notification')
            ->line('Failed login attempt at ' . config('app.url'));
    }
}
