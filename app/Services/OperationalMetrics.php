<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class OperationalMetrics
{
    /**
     * @return array{
     *     from:string,
     *     to:string,
     *     scope:string,
     *     organization_id:int|null,
     *     total_events:int,
     *     evaluation_events:int,
     *     auditor_events:int,
     *     validation_events:int,
     *     subscription_events:int,
     *     public_discovery_events:int,
     *     notification_events:int,
     *     notification_unread:int,
     *     notification_failure_events:int,
     *     notification_recovery_events:int,
     *     action_queue_visible_items:int|null,
     *     monitoring_events:int,
     *     failure_events:int,
     *     retry_events:int,
     *     events_by_type:array<string,int>,
     *     lifecycle_durations:array<string,array{count:int,average_seconds:float,minimum_seconds:float,maximum_seconds:float}>,
     *     duplicate_events:int,
     *     out_of_order_events:int
     *     }
     */
    public function forUser(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $organizationId = null,
    ): array {
        if (! $user->isPlatformAdmin() && $organizationId === null) {
            throw new AuthorizationException('An organization scope is required for non-platform administrators.');
        }

        if (
            ! $user->isPlatformAdmin()
            && ! $user->organizations()->whereKey($organizationId)->exists()
        ) {
            throw new AuthorizationException('The user does not belong to the requested organization.');
        }

        if ($to->lessThanOrEqualTo($from)) {
            throw new InvalidArgumentException('The metric end time must be after the start time.');
        }

        return $this->aggregate($from, $to, $organizationId);
    }

    /**
     * @return array{
     *     from:string,
     *     to:string,
     *     scope:string,
     *     organization_id:int|null,
     *     total_events:int,
     *     evaluation_events:int,
     *     auditor_events:int,
     *     validation_events:int,
     *     subscription_events:int,
     *     public_discovery_events:int,
     *     notification_events:int,
     *     notification_unread:int,
     *     notification_failure_events:int,
     *     notification_recovery_events:int,
     *     action_queue_visible_items:int|null,
     *     monitoring_events:int,
     *     failure_events:int,
     *     retry_events:int,
     *     events_by_type:array<string,int>,
     *     lifecycle_durations:array<string,array{count:int,average_seconds:float,minimum_seconds:float,maximum_seconds:float}>,
     *     duplicate_events:int,
     *     out_of_order_events:int
     *     }
     */
    private function aggregate(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $organizationId,
    ): array {
        $query = AuditLog::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($organizationId !== null) {
            $query->whereJsonContains('metadata->organization_id', $organizationId);
        }

        /** @var Collection<int, AuditLog> $logs */
        $logs = $query->get([
            'id',
            'event',
            'metadata',
            'created_at',
            'auditable_type',
            'auditable_id',
        ]);

        /** @var array<string, int> $eventsByType */
        $eventsByType = [];
        $failureEvents = 0;
        $retryEvents = 0;
        $monitoringEvents = 0;
        $evaluationEvents = 0;
        $auditorEvents = 0;
        $validationEvents = 0;
        $subscriptionEvents = 0;
        $publicDiscoveryEvents = 0;
        $duplicateEvents = 0;
        $outOfOrderEvents = 0;
        /** @var array<string, true> $seenSignatures */
        $seenSignatures = [];
        /** @var array<string, list<CarbonImmutable>> $lifecycleStarts */
        $lifecycleStarts = [];
        /** @var array<string, list<float>> $lifecycleDurations */
        $lifecycleDurations = [];

        foreach ($logs as $log) {
            $event = (string) $log->getAttribute('event');
            $eventsByType[$event] = ($eventsByType[$event] ?? 0) + 1;

            if (
                str_starts_with($event, 'evaluation.')
                || str_starts_with($event, 'evaluation_request.')
            ) {
                $evaluationEvents++;
            }
            if (
                str_starts_with($event, 'auditor.')
                || str_starts_with($event, 'auditor_')
            ) {
                $auditorEvents++;
            }
            if (str_starts_with($event, 'validation.')) {
                $validationEvents++;
            }
            if (
                str_starts_with($event, 'subscription.')
                || str_starts_with($event, 'billing.subscription.')
            ) {
                $subscriptionEvents++;
            }
            if (
                str_starts_with($event, 'public_verification.')
                || str_starts_with($event, 'public_directory.')
                || str_starts_with($event, 'discovery.')
                || str_starts_with($event, 'public.discovery.')
                || str_starts_with($event, 'directory.')
            ) {
                $publicDiscoveryEvents++;
            }
            if (
                str_starts_with($event, 'validation_trust_monitor.')
                || str_starts_with($event, 'monitoring.')
                || str_starts_with($event, 'validation.monitor')
            ) {
                $monitoringEvents++;
            }
            if (preg_match('/(?:^|[._-])(failed|failure|error)$/i', $event) === 1) {
                $failureEvents++;
            }
            if (str_contains(strtolower($event), 'retry')) {
                $retryEvents++;
            }

            $signature = implode('|', [
                $event,
                (string) $log->getAttribute('auditable_type'),
                (string) $log->getAttribute('auditable_id'),
                (string) $log->getAttribute('created_at'),
            ]);
            if (isset($seenSignatures[$signature])) {
                $duplicateEvents++;
            }
            $seenSignatures[$signature] = true;

            if (preg_match('/^(.+)\.(started|completed|failed)$/', $event, $matches) !== 1) {
                continue;
            }

            $base = (string) $matches[1];
            $state = (string) $matches[2];
            $key = implode('|', [
                $base,
                (string) $log->getAttribute('auditable_type'),
                (string) $log->getAttribute('auditable_id'),
            ]);

            $createdAt = $log->getAttribute('created_at');
            if (! $createdAt instanceof DateTimeInterface) {
                $outOfOrderEvents++;

                continue;
            }
            $createdAt = CarbonImmutable::instance($createdAt);

            if ($state === 'started') {
                $lifecycleStarts[$key][] = $createdAt;

                continue;
            }

            if (empty($lifecycleStarts[$key])) {
                $outOfOrderEvents++;

                continue;
            }

            $startedAt = array_shift($lifecycleStarts[$key]);
            if ($startedAt === null || $createdAt->lessThan($startedAt)) {
                $outOfOrderEvents++;

                continue;
            }

            $lifecycleDurations[$base][] = (float) $startedAt->diffInSeconds($createdAt);
        }

        /** @var array<string, array{count:int,average_seconds:float,minimum_seconds:float,maximum_seconds:float}> $durationSummary */
        $durationSummary = [];
        foreach ($lifecycleDurations as $event => $durations) {
            if ($durations === []) {
                continue;
            }

            $durationSummary[$event] = [
                'count' => count($durations),
                'average_seconds' => round(array_sum($durations) / count($durations), 2),
                'minimum_seconds' => min($durations),
                'maximum_seconds' => max($durations),
            ];
        }

        $notificationQuery = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to);

        if ($organizationId !== null) {
            $organizationUserIds = User::query()
                ->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))
                ->select('id');

            $notificationQuery->whereIn('notifiable_id', $organizationUserIds);
        }

        $notificationEvents = $notificationQuery->count();
        $notificationUnread = (clone $notificationQuery)->whereNull('read_at')->count();
        $notificationFailureEvents = (clone $notificationQuery)
            ->where(function ($query): void {
                $query
                    ->whereJsonContains('data->event_type', 'trust_monitoring_failed')
                    ->orWhereJsonContains('data->event_type', 'subscription_payment_failed');
            })
            ->count();
        $notificationRecoveryEvents = (clone $notificationQuery)
            ->where(function ($query): void {
                $query
                    ->whereJsonContains('data->event_type', 'trust_monitoring_recovered')
                    ->orWhereJsonContains('data->event_type', 'subscription_recovered');
            })
            ->count();

        $actionQueueVisibleItems = $organizationId === null
            ? app(RoleActionQueue::class)->forPlatformAdmin()->count()
            : null;

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'scope' => $organizationId === null ? 'platform' : 'organization',
            'organization_id' => $organizationId,
            'total_events' => $logs->count(),
            'evaluation_events' => $evaluationEvents,
            'auditor_events' => $auditorEvents,
            'validation_events' => $validationEvents,
            'subscription_events' => $subscriptionEvents,
            'public_discovery_events' => $publicDiscoveryEvents,
            'notification_events' => $notificationEvents,
            'notification_unread' => $notificationUnread,
            'notification_failure_events' => $notificationFailureEvents,
            'notification_recovery_events' => $notificationRecoveryEvents,
            'action_queue_visible_items' => $actionQueueVisibleItems,
            'monitoring_events' => $monitoringEvents,
            'failure_events' => $failureEvents,
            'retry_events' => $retryEvents,
            'events_by_type' => $eventsByType,
            'lifecycle_durations' => $durationSummary,
            'duplicate_events' => $duplicateEvents,
            'out_of_order_events' => $outOfOrderEvents,
        ];
    }
}
