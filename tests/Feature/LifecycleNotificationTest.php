<?php

declare(strict_types=1);

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\WorkflowNotificationInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

test('notification preferences default to enabled and can be changed per category', function () {
    $user = User::factory()->create();

    expect($user->canReceiveNotification(NotificationCategory::Trust))->toBeTrue();

    $user->setNotificationPreference(NotificationCategory::Trust, false);
    $user->refresh();

    expect($user->canReceiveNotification(NotificationCategory::Trust))->toBeFalse()
        ->and($user->canReceiveNotification(NotificationCategory::Billing))->toBeTrue();
});

test('workflow notification preserves an authorized action URL', function () {
    $user = User::factory()->create();
    $notification = new WorkflowNotification(
        NotificationCategory::Trust,
        NotificationEventType::ValidationSuspended,
        'Validation suspended',
        'The validation has been suspended.',
        ['validation_id' => 10, 'evaluation_id' => 20],
        '/creator/evaluations/20/report',
    );

    expect($notification->toDatabase($user)['action_url'])->toBe('/creator/evaluations/20/report');
});

test('notification inbox exposes action URLs and current stale state', function () {
    $user = User::factory()->create();

    DatabaseNotification::query()->create([
        'id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        'type' => WorkflowNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => [
            'category' => 'trust',
            'event_type' => 'validation_suspended',
            'title' => 'Validation suspended',
            'body' => 'The validation has been suspended.',
            'context' => ['validation_id' => 999999],
            'action_url' => '/creator/evaluations/20/report',
        ],
    ]);

    $notification = app(WorkflowNotificationInbox::class)->for($user)->first();

    expect($notification['action_url'])->toBe('/creator/evaluations/20/report')
        ->and($notification['stale'])->toBeTrue();
});
