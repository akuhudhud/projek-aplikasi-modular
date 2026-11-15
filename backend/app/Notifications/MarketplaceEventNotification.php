<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MarketplaceEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $event,
        private string $title,
        private string $message,
        private string $requestId,
        private ?string $quotationId = null,
        private ?string $taskId = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'request_id' => $this->requestId,
            'quotation_id' => $this->quotationId,
            'task_id' => $this->taskId,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
