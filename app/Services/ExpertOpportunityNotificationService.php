<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use App\Models\ExpertOpportunity;
use App\Models\ExpertOpportunityParticipation;
use App\Models\User;
use App\Notifications\WorkflowNotification;

final class ExpertOpportunityNotificationService
{
    public function published(ExpertOpportunity $opportunity): void
    {
        $this->admins(
            NotificationCategory::Assignment,
            NotificationEventType::ExpertOpportunityPublished,
            'Expert opportunity published',
            sprintf('A new expert opportunity is available: %s.', $opportunity->title),
            ['expert_opportunity_id' => $opportunity->getKey()],
        );
    }

    public function application(ExpertOpportunityParticipation $participation): void
    {
        $participation->loadMissing('opportunity', 'auditorProfile.auditor');
        $this->admins(
            NotificationCategory::Assignment,
            NotificationEventType::ExpertOpportunityApplication,
            'Expert opportunity application',
            sprintf('%s applied to %s.', $participation->auditorProfile->auditor->name, $participation->opportunity->title),
            [
                'expert_opportunity_id' => $participation->opportunity->getKey(),
                'participation_id' => $participation->getKey(),
            ],
        );
    }

    public function selected(ExpertOpportunityParticipation $participation): void
    {
        $participation->loadMissing('opportunity', 'auditorProfile.auditor');
        $this->send(
            $participation->auditorProfile->auditor,
            NotificationCategory::Assignment,
            NotificationEventType::ExpertOpportunitySelection,
            'Expert opportunity selected',
            sprintf('You have been selected for %s.', $participation->opportunity->title),
            [
                'expert_opportunity_id' => $participation->opportunity->getKey(),
                'participation_id' => $participation->getKey(),
            ],
        );
    }

    /** @param array<string, int|string|null> $context */
    private function admins(NotificationCategory $category, NotificationEventType $event, string $title, string $body, array $context): void
    {
        foreach (User::query()->whereNotNull('platform_role')->cursor() as $user) {
            $this->send($user, $category, $event, $title, $body, $context);
        }
    }

    /** @param array<string, int|string|null> $context */
    private function send(User $user, NotificationCategory $category, NotificationEventType $event, string $title, string $body, array $context): void
    {
        $user->notify(new WorkflowNotification($category, $event, $title, $body, $context));
    }
}
