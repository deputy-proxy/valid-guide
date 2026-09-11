<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClarificationSubmitted;
use App\Services\WorkflowNotificationService;

final class SendClarificationNotification
{
    public function handle(ClarificationSubmitted $event): void
    {
        app(WorkflowNotificationService::class)->clarificationSubmitted($event->request);
    }
}
