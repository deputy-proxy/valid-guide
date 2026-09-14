<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Filament\Pages\OperationalMetricsPage;
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
            'auditable_type' => User::class,
            'auditable_id' => $admin->getKey(),
            'metadata' => ['organization_id' => $organizationId],
            'created_at' => $from->addSeconds($offset),
        ]);
    }

    AuditLog::create([
        'event' => 'public_verification.published',
        'auditable_type' => User::class,
        'auditable_id' => $admin->getKey(),
        'metadata' => [],
        'created_at' => $from->addSeconds(120),
    ]);

    $metrics = app(OperationalMetrics::class)->forUser($admin, $from, $to, 1);

    expect($metrics['scope'])->toBe('organization')
        ->and($metrics['organization_id'])->toBe(1)
        ->and($metrics['total_events'])->toBe(12)
        ->and($metrics['evaluation_events'])->toBe(3)
        ->and($metrics['auditor_events'])->toBe(1)
        ->and($metrics['validation_events'])->toBe(1)
        ->and($metrics['subscription_events'])->toBe(1)
        ->and($metrics['public_discovery_events'])->toBe(0)
        ->and($metrics['notification_events'])->toBe(0)
        ->and($metrics['notification_unread'])->toBe(0)
        ->and($metrics['notification_failure_events'])->toBe(0)
        ->and($metrics['notification_recovery_events'])->toBe(0)
        ->and($metrics['action_queue_visible_items'])->toBeNull()
        ->and($metrics['monitoring_events'])->toBe(1)
        ->and($metrics['failure_events'])->toBe(2)
        ->and($metrics['retry_events'])->toBe(1)
        ->and($metrics['duplicate_events'])->toBe(1)
        ->and($metrics['out_of_order_events'])->toBe(1)
        ->and($metrics['lifecycle_durations']['evaluation']['count'])->toBe(1)
        ->and($metrics['lifecycle_durations']['evaluation']['average_seconds'])->toBe(10.0);
});

it('does not expose notification payloads in the aggregate response', function (): void {
    $admin = User::factory()->create()->forceFill(['platform_role' => PlatformRole::Admin]);
    $admin->save();

    $metrics = app(OperationalMetrics::class)->forUser(
        $admin,
        CarbonImmutable::parse('2026-09-01 00:00:00'),
        CarbonImmutable::parse('2026-09-02 00:00:00'),
    );

    expect($metrics)->not->toHaveKey('title')
        ->and($metrics)->not->toHaveKey('body')
        ->and($metrics['notification_events'])->toBe(0)
        ->and($metrics['notification_unread'])->toBe(0)
        ->and($metrics['notification_failure_events'])->toBe(0)
        ->and($metrics['notification_recovery_events'])->toBe(0);
});

it('returns an empty metric set for an empty interval', function (): void {
    $admin = User::factory()->create()->forceFill(['platform_role' => PlatformRole::Admin]);
    $admin->save();

    $metrics = app(OperationalMetrics::class)->forUser(
        $admin,
        CarbonImmutable::parse('2026-09-01 00:00:00'),
        CarbonImmutable::parse('2026-09-02 00:00:00'),
    );

    expect($metrics['total_events'])->toBe(0)
        ->and($metrics['events_by_type'])->toBe([])
        ->and($metrics['lifecycle_durations'])->toBe([])
        ->and($metrics['duplicate_events'])->toBe(0)
        ->and($metrics['out_of_order_events'])->toBe(0);
});

it('uses an inclusive start and exclusive end time boundary', function (): void {
    $admin = User::factory()->create()->forceFill(['platform_role' => PlatformRole::Admin]);
    $admin->save();

    $from = CarbonImmutable::parse('2026-09-01 00:00:00');
    $to = CarbonImmutable::parse('2026-09-02 00:00:00');

    AuditLog::create([
        'event' => 'boundary.in',
        'auditable_type' => User::class,
        'auditable_id' => $admin->getKey(),
        'created_at' => $from,
    ]);
    AuditLog::create([
        'event' => 'boundary.out',
        'auditable_type' => User::class,
        'auditable_id' => $admin->getKey(),
        'created_at' => $to,
    ]);

    $metrics = app(OperationalMetrics::class)->forUser($admin, $from, $to);

    expect($metrics['total_events'])->toBe(1)
        ->and($metrics['events_by_type'])->toBe(['boundary.in' => 1]);
});

it('restricts the reporting page to platform administrators', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->create()->forceFill(['platform_role' => PlatformRole::Admin]);
    $admin->save();

    $this->actingAs($user);
    expect(OperationalMetricsPage::canAccess())->toBeFalse();

    $this->actingAs($admin);
    expect(OperationalMetricsPage::canAccess())->toBeTrue();
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
