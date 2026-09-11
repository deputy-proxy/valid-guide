<?php

declare(strict_types=1);

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\RoleActionQueue;
use App\Services\WorkflowNotificationInbox;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

test('workflow notifications persist only structured workflow context', function () {
    $user = User::factory()->create();

    $notification = new WorkflowNotification(
        NotificationCategory::Submission,
        NotificationEventType::AuditorEvaluationSubmitted,
        'Auditor evaluation submitted',
        'An Auditor evaluation requires platform review.',
        ['auditor_evaluation_id' => 10, 'evaluation_id' => 20],
    );

    $data = $notification->toDatabase($user);

    expect($data)->toMatchArray([
        'category' => 'submission',
        'event_type' => 'auditor_evaluation_submitted',
        'title' => 'Auditor evaluation submitted',
        'body' => 'An Auditor evaluation requires platform review.',
    ]);
    expect($data['context'])->toBe(['auditor_evaluation_id' => 10, 'evaluation_id' => 20]);
});

test('notification inbox is isolated to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DatabaseNotification::query()->create([
        'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'type' => WorkflowNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => [
            'category' => 'submission',
            'event_type' => 'auditor_evaluation_submitted',
            'title' => 'Private notification',
            'body' => 'Only the intended user can see this.',
            'context' => ['auditor_evaluation_id' => 999999],
        ],
    ]);

    DatabaseNotification::query()->create([
        'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'type' => WorkflowNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => [
            'category' => 'submission',
            'event_type' => 'auditor_evaluation_submitted',
            'title' => 'Other notification',
            'body' => 'This must not be visible.',
            'context' => ['auditor_evaluation_id' => 999998],
        ],
    ]);

    $notifications = app(WorkflowNotificationInbox::class)->for($user);

    expect($notifications)->toHaveCount(1);
    expect($notifications->first()['title'])->toBe('Private notification');
});

test('notifications can be marked read only through the owning user relation', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DatabaseNotification::query()->create([
        'id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        'type' => WorkflowNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['event_type' => 'unknown', 'category' => 'system', 'title' => 'Test', 'body' => 'Test', 'context' => []],
    ]);

    expect(fn () => app(WorkflowNotificationInbox::class)->markRead($otherUser, 'cccccccc-cccc-cccc-cccc-cccccccccccc'))
        ->toThrow(ModelNotFoundException::class);

    app(WorkflowNotificationInbox::class)->markRead($user, 'cccccccc-cccc-cccc-cccc-cccccccccccc');

    expect(DatabaseNotification::query()->find('cccccccc-cccc-cccc-cccc-cccccccccccc')?->read_at)->not->toBeNull();
});

test('unknown notification events are treated as stale', function () {
    $user = User::factory()->create();

    DatabaseNotification::query()->create([
        'id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        'type' => WorkflowNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['event_type' => 'unknown', 'category' => 'system', 'title' => 'Unknown', 'body' => 'Unknown', 'context' => []],
    ]);

    $notification = app(WorkflowNotificationInbox::class)->for($user)->first();

    expect($notification['stale'])->toBeTrue();
});

test('users without an authorized role have an empty action queue', function () {
    $user = User::factory()->create();

    expect(app(RoleActionQueue::class)->for($user))->toBeEmpty();
});
