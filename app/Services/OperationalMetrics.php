<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

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
     *     action_queue_events:int,
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
            throw new \InvalidArgumentException('The metric end time must be after the start time.');
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
     *     action_queue_events:int,
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

        $eventsByType = [];
        $failureEvents = 0;
        $retryEvents = 0;
        $notificationEvents = 0;
        $actionQueueEvents = 0;
        $monitoringEvents = 0;
        $evaluationEvents = 0;
        $auditorEvents = 0;
        $validationEvents = 0;
        $subscriptionEvents = 0;
        $publicDiscoveryEvents = 0;
        $duplicateEvents = 0;
        $outOfOrderEvents = 0;
        $seenSignatures = [];
        $lifecycleStarts = [];
        $lifecycleDurations = [];

        foreach ($logs as $log) {
            $event = (string) $log->getAttribute('event');
            $eventsByType[$event] = ($eventsByType[$event] ?? 0) + 1;

            if (str_starts_with($event, 'evaluation.')) {
                $evaluationEvents++;
            }
            if (str_starts_with($event, 'auditor.')) {
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
                str_starts_with($event, 'discovery.')
                || str_starts_with($event, 'public.discovery.')
                || str_starts_with($event, 'directory.')
            ) {
                $publicDiscoveryEvents++;
            }
            if (str_starts_with($event, 'notification.')) {
                $notificationEvents++;
            }
            if (str_starts_with($event, 'action_queue.')) {
                $actionQueueEvents++;
            }
            if (
                str_starts_with($event, 'monitoring.')
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

            $base = $matches[1];
            $state = $matches[2];
            $key = implode('|', [
                $base,
                (string) $log->getAttribute('auditable_type'),
                (string) $log->getAttribute('auditable_id'),
            ]);

            $createdAt = $log->getAttribute('created_at');
            if (! $createdAt instanceof \DateTimeInterface) {
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
            if (! $startedAt instanceof CarbonImmutable || $createdAt->lessThan($startedAt)) {
                $outOfOrderEvents++;

                continue;
            }

            $lifecycleDurations[$base][] = (float) $startedAt->diffInSeconds($createdAt);
        }

        $durationSummary = [];
        foreach ($lifecycleDurations as $event => $durations) {
            $durationSummary[$event] = [
                'count' => count($durations),
                'average_seconds' => round(array_sum($durations) / count($durations), 2),
                'minimum_seconds' => min($durations),
                'maximum_seconds' => max($durations),
            ];
        }

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
            'action_queue_events' => $actionQueueEvents,
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
