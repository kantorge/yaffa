<?php

namespace App\Notifications;

use App\Models\GoogleDriveConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleDriveImportFailed extends Notification
{
    use Queueable;

    public function __construct(public GoogleDriveConfig $config, public string $error)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject(__('mail.google_drive_import_failed.subject'))
            ->markdown('emails.google-drive-import-failed', [
                'user' => $notifiable,
                'config' => $this->config,
                'error' => $this->error,
            ]);
    }
}
