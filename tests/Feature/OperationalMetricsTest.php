<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OperationalMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('aggregates operational audit signals without crossing organization boundaries', function (): void {
    $admin = User::factory()->create()->forceFill(['platform_role' => PlatformRole::Admin]);
    $admin->save();

    $from = CarbonImmutable::parse('2026-09-01 00:00:00');
    $to = CarbonImmutable::parse('2026-09-02 00:00:00');

    $events = [
        ['evaluation.started', 0, 1],
        ['evaluation.completed', 10, 1],
        ['evaluation.failed', 20, 1],
        ['notification.sent', 30, 1],
        ['notification.sent', 30, 1],
        ['action_queue.failed', 40, 1],
        ['subscription.renewed', 50, 1],
        ['discovery.viewed', 60, 1],
        ['auditor.reviewed', 70, 1],
        ['validation.completed', 80, 1],
        ['monitoring.alert', 90, 1],
        ['job.retry', 100, 1],
        ['evaluation.completed', 110, 2],
    ];

    foreach ($events as [$event, $offset, $organizationId]) {
        AuditLog::create([
            'event' => $event,
            'metadata' => ['organization_id' => $organizationId],
            'created_at' => $from->addSeconds($offset),
        ]);
    }

    $metrics = app(OperationalMetrics::class)->forUser($admin, $from, $to, 1);

    expect($metrics['scope'])->toBe('organization')
        ->and($metrics['organization_id'])->toBe(1)
        ->and($metrics['total_events'])->toBe(12)
        ->and($metrics['evaluation_events'])->toBe(3)
        ->and($metrics['auditor_events'])->toBe(1)
        ->and($metrics['validation_events'])->toBe(1)
        ->and($metrics['subscription_events'])->toBe(1)
        ->and($metrics['public_discovery_events'])->toBe(1)
        ->and($metrics['notification_events'])->toBe(2)
        ->and($metrics['action_queue_events'])->toBe(1)
        ->and($metrics['monitoring_events'])->toBe(1)
        ->and($metrics['failure_events'])->toBe(2)
        ->and($metrics['retry_events'])->toBe(1)
        ->and($metrics['duplicate_events'])->toBe(1)
        ->and($metrics['out_of_order_events'])->toBe(1)
        ->and($metrics['lifecycle_durations']['evaluation']['count'])->toBe(1)
        ->and($metrics['lifecycle_durations']['evaluation']['average_seconds'])->toBe(10.0);
});

it('requires a tenant scope for non-platform administrators', function (): void {
    $user = User::factory()->create();

    expect(fn (): array => app(OperationalMetrics::class)->forUser(
        $user,
        CarbonImmutable::parse('2026-09-01 00:00:00'),
        CarbonImmutable::parse('2026-09-02 00:00:00'),
    ))->toThrow(AuthorizationException::class);
});

it('rejects an organization scope the user does not belong to', function (): void {
    $user = User::factory()->create();

    expect(fn (): array => app(OperationalMetrics::class)->forUser(
        $user,
        CarbonImmutable::parse('2026-09-01 00:00:00'),
        CarbonImmutable::parse('2026-09-02 00:00:00'),
        999999,
    ))->toThrow(AuthorizationException::class);
});
