<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditorEvaluationSubmitted;
use App\Services\WorkflowNotificationService;

final class SendAuditorEvaluationSubmissionNotification
{
    public function handle(AuditorEvaluationSubmitted $event): void
    {
        app(WorkflowNotificationService::class)->auditorEvaluationSubmitted($event->auditorEvaluation);
    }
}
