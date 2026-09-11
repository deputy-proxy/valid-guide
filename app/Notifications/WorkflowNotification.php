<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, int|string|null> $context */
    public function __construct(
        public readonly NotificationCategory $category,
        public readonly NotificationEventType $eventType,
        public readonly string $title,
        public readonly string $body,
        public readonly array $context = [],
    ) {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => $this->category->value,
            'event_type' => $this->eventType->value,
            'title' => $this->title,
            'body' => $this->body,
            'context' => $this->context,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
