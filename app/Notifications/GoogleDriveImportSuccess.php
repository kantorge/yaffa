<?php

namespace App\Notifications;

use App\Models\GoogleDriveConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleDriveImportSuccess extends Notification
{
    use Queueable;

    public function __construct(public GoogleDriveConfig $config)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject(__('Google Drive Import Succeeded'))
            ->line(__('Your Google Drive import completed successfully for folder: :folder', ['folder' => $this->config->folder_id]))
            ->line(__('You can now review the imported documents.'));
    }
}
