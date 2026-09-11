<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReportDelivered;
use App\Services\WorkflowNotificationService;

final class SendReportDeliveryNotification
{
    public function handle(ReportDelivered $event): void
    {
        app(WorkflowNotificationService::class)->reportDelivered($event->report);
    }
}
