<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use App\Enums\PlatformRole;
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
        $expert = $participation->auditorProfile?->auditor;
        $opportunity = $participation->opportunity;
        if (! $expert instanceof User || ! $opportunity instanceof ExpertOpportunity) {
            throw new DomainStateTransitionException('The opportunity application has incomplete provenance.');
        }

        $this->admins(
            NotificationCategory::Assignment,
            NotificationEventType::ExpertOpportunityApplication,
            'Expert opportunity application',
            sprintf('%s applied to %s.', $expert->name, $opportunity->title),
            [
                'expert_opportunity_id' => $opportunity->getKey(),
                'participation_id' => $participation->getKey(),
            ],
        );
    }

    public function selected(ExpertOpportunityParticipation $participation): void
    {
        $participation->loadMissing('opportunity', 'auditorProfile.auditor');
        $expert = $participation->auditorProfile?->auditor;
        $opportunity = $participation->opportunity;
        if (! $expert instanceof User || ! $opportunity instanceof ExpertOpportunity) {
            throw new DomainStateTransitionException('The opportunity selection has incomplete provenance.');
        }

        $this->send(
            $expert,
            NotificationCategory::Assignment,
            NotificationEventType::ExpertOpportunitySelection,
            'Expert opportunity selected',
            sprintf('You have been selected for %s.', $opportunity->title),
            [
                'expert_opportunity_id' => $opportunity->getKey(),
                'participation_id' => $participation->getKey(),
            ],
        );
    }

    /** @param array<string, int|string|null> $context */
    private function admins(NotificationCategory $category, NotificationEventType $event, string $title, string $body, array $context): void
    {
        User::query()->where('platform_role', PlatformRole::Admin)->cursor()->each(function (User $user) use ($category, $event, $title, $body, $context): void {
            $this->send($user, $category, $event, $title, $body, $context);
        });
    }

    /** @param array<string, int|string|null> $context */
    private function send(User $user, NotificationCategory $category, NotificationEventType $event, string $title, string $body, array $context): void
    {
        $user->notify(new WorkflowNotification($category, $event, $title, $body, $context));
    }
}
