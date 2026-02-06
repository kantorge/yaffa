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
        return (new MailMessage)
            ->subject(__('Google Drive Import Failed'))
            ->line(__('Your Google Drive import failed for folder: :folder', ['folder' => $this->config->folder_id]))
            ->line(__('Error: :error', ['error' => $this->error]))
            ->line(__('Please check your Google Drive configuration.'));
    }
}
