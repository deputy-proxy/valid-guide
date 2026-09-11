<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DisputeSubmitted;
use App\Services\WorkflowNotificationService;

final class SendDisputeNotification
{
    public function handle(DisputeSubmitted $event): void
    {
        app(WorkflowNotificationService::class)->disputeSubmitted($event->dispute);
    }
}
