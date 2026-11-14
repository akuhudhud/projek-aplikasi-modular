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
    ) {
    }

    /**
     * Saluran penghantaran notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Data yang disimpan dalam jadual notifications.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'request_id' => $this->requestId,
            'quotation_id' => $this->quotationId,
        ];
    }

    /**
     * Data untuk representasi notifikasi.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
