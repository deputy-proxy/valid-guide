<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditorAssignmentCreated;
use App\Services\WorkflowNotificationService;

final class SendAuditorAssignmentNotification
{
    public function handle(AuditorAssignmentCreated $event): void
    {
        app(WorkflowNotificationService::class)->auditorAssignmentCreated($event->assignment);
    }
}
