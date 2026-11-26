<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminOperationalNotification extends Notification
{
    use Queueable;

    public const CATEGORY_REQUEST = 'REQUEST';

    public const CATEGORY_TASK = 'TASK';

    public const CATEGORY_RUNNER_APPLICATION = 'RUNNER_APPLICATION';

    public const CATEGORY_IDENTITY_VERIFICATION = 'IDENTITY_VERIFICATION';

    public const CATEGORY_LICENSE_VERIFICATION = 'LICENSE_VERIFICATION';

    public const SEVERITY_INFO = 'INFO';

    public const SEVERITY_WARNING = 'WARNING';

    public const SEVERITY_CRITICAL = 'CRITICAL';

    public function __construct(
        private string $event,
        private string $category,
        private string $title,
        private string $message,
        private string $severity = self::SEVERITY_INFO,
        private ?string $resourceType = null,
        private ?string $resourceId = null,
        private ?string $actionPath = null,
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
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'action_path' => $this->actionPath,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
