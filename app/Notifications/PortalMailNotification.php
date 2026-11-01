<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $payload)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->payload['title'])->greeting('Halo, '.$notifiable->name)->line($this->payload['body'])->action('Buka RumahIjo', url($this->payload['url']));
    }
}
