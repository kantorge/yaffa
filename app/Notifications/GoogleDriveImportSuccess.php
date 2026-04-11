<?php

namespace App\Notifications;

use App\Models\GoogleDriveConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleDriveImportSuccess extends Notification
{
    use Queueable;

    /**
     * @param array{imported:int, skipped_existing:int, skipped_unsupported:int, skipped_too_large:int, failed_downloads:int} $stats
     */
    public function __construct(public GoogleDriveConfig $config, public array $stats)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject(__('mail.google_drive_import_success.subject'))
            ->markdown('emails.google-drive-import-success', [
                'user' => $notifiable,
                'config' => $this->config,
                'stats' => $this->stats,
            ]);
    }
}
