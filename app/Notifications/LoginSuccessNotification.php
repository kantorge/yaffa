<?php

namespace App\Notifications;

use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginSuccessNotification extends Notification
{
    protected Login $event;

    /**
     * Create a new notification instance.
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
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return MailMessage
     */
    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->subject('YAFFA info - successful login')
            ->line('Successful login at ' . config('app.url') . ' for user ' . $this->event->user->email);
    }
}
