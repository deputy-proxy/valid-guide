<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ValidationStatus;
use App\Enums\ValidationTrustMonitorCadence;
use App\Enums\ValidationTrustMonitorEventType;
use App\Enums\ValidationTrustMonitorStatus;
use App\Models\User;
use App\Services\ValidationStateTransition;
use App\Services\ValidationTrustMonitoring;
use App\Services\ValidationTrustMonitoringException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function trustMonitorCreator($validation): array
{
    $organization = $validation->productRelease->product->organization;
    $creator = User::factory()->create();
    $organization->users()->attach($creator, ['role' => OrganizationRole::Editor->value]);

    return [$organization, $creator];
}

it('configures a tenant-scoped monitor and records an auditable baseline', function () {
    [$validation] = issuedValidation();
    [$organization, $creator] = trustMonitorCreator($validation);

    $monitor = app(ValidationTrustMonitoring::class)->configure($validation, $creator, ValidationTrustMonitorCadence::Hourly);

    expect($monitor->organization_id)->toBe($organization->id)
        ->and($monitor->validation_id)->toBe($validation->id)
        ->and($monitor->cadence)->toBe(ValidationTrustMonitorCadence::Hourly)
        ->and($monitor->status)->toBe(ValidationTrustMonitorStatus::Active);

    app(ValidationTrustMonitoring::class)->runOne($monitor, now());

    expect($monitor->refresh()->observed_fingerprint)->not->toBeNull()
        ->and($monitor->status)->toBe(ValidationTrustMonitorStatus::Active)
        ->and($monitor->events()->where('type', ValidationTrustMonitorEventType::BaselineRecorded->value)->count())->toBe(1);

    expect(DB::table('audit_logs')->where('event', 'validation_trust_monitor.configured')->count())->toBe(1);
});

it('detects trust-state changes without mutating evaluation outcomes or the persisted public snapshot', function () {
    [$validation, $platformAdmin] = issuedValidation();
    [$organization, $creator] = trustMonitorCreator($validation);
    $monitoring = app(ValidationTrustMonitoring::class);
    $monitor = $monitoring->configure($validation, $creator, ValidationTrustMonitorCadence::Hourly);

    $monitoring->runOne($monitor, now());
    $publicRecord = $validation->publicVerificationRecord()->firstOrFail();
    $originalSnapshot = $publicRecord->snapshot;
    $originalDecision = $validation->evaluation->decision;
    $originalScore = $validation->evaluation->overall_score;

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Suspended,
        $platformAdmin,
        'Temporary verification hold.',
    );

    $monitor->refresh();
    $monitor->next_check_at = now()->subMinute();
    $monitor->save();
    $monitoring->runOne($monitor, now());

    $validation->refresh();
    $publicRecord->refresh();

    expect($monitor->refresh()->events()->where('type', ValidationTrustMonitorEventType::TrustStateChanged->value)->count())->toBe(1)
        ->and($validation->status)->toBe(ValidationStatus::Suspended)
        ->and($validation->evaluation->decision)->toBe($originalDecision)
        ->and((string) $validation->evaluation->overall_score)->toBe((string) $originalScore)
        ->and($publicRecord->snapshot)->not->toBe($originalSnapshot)
        ->and($publicRecord->snapshot['status'])->toBe(ValidationStatus::Suspended->value)
        ->and($organization->refresh()->id)->toBe($monitor->organization_id);
});

it('suppresses duplicate events when the observed state is unchanged', function () {
    [$validation] = issuedValidation();
    [, $creator] = trustMonitorCreator($validation);
    $monitoring = app(ValidationTrustMonitoring::class);
    $monitor = $monitoring->configure($validation, $creator);

    $monitoring->runOne($monitor, now());
    $baselineCount = $monitor->refresh()->events()->count();

    $monitor->next_check_at = now()->subMinute();
    $monitor->save();
    $monitoring->runOne($monitor, now());

    expect($monitor->refresh()->events()->count())->toBe($baselineCount);
});

it('enforces tenant boundaries for monitor configuration', function () {
    [$validation] = issuedValidation();
    [$organization] = trustMonitorCreator($validation);
    $otherOrganizationId = DB::table('organizations')->insertGetId([
        'name' => 'Other organization',
        'slug' => 'other-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherUser = User::factory()->create();
    DB::table('organization_memberships')->insert([
        'organization_id' => $otherOrganizationId,
        'user_id' => $otherUser->id,
        'role' => OrganizationRole::Admin->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(ValidationTrustMonitoring::class)->configure($validation, $otherUser))
        ->toThrow(AuthorizationException::class)
        ->and($organization->id)->not->toBe($otherOrganizationId);
});

it('handles invalid state, recovery and cancellation without mutating validation history', function () {
    [$validation] = issuedValidation();
    [$organization, $creator] = trustMonitorCreator($validation);
    $monitoring = app(ValidationTrustMonitoring::class);
    $monitor = $monitoring->configure($validation, $creator);
    $monitoring->runOne($monitor, now());

    $snapshot = $validation->publicVerificationRecord()->firstOrFail()->snapshot;
    $originalStatus = $validation->status;
    $originalDecision = $validation->evaluation->decision;

    DB::table('public_verification_records')->where('id', $validation->publicVerificationRecord->id)->update(['snapshot' => null]);
    $monitor->refresh()->forceFill(['next_check_at' => now()->subMinute()])->save();
    $monitoring->runOne($monitor, now());

    expect($monitor->refresh()->status)->toBe(ValidationTrustMonitorStatus::Invalid)
        ->and($monitor->events()->where('type', ValidationTrustMonitorEventType::MonitoringFailed->value)->count())->toBe(1);

    DB::table('public_verification_records')->where('id', $validation->publicVerificationRecord->id)->update(['snapshot' => json_encode($snapshot)]);
    $monitor->refresh()->forceFill(['next_check_at' => now()->subMinute()])->save();
    $monitoring->runOne($monitor, now());

    expect($monitor->refresh()->status)->toBe(ValidationTrustMonitorStatus::Active)
        ->and($monitor->events()->where('type', ValidationTrustMonitorEventType::MonitoringRecovered->value)->count())->toBe(1);

    $monitoring->cancel($monitor, $creator, 'Creator requested monitoring pause.');

    $validation->refresh();
    expect($monitor->refresh()->status)->toBe(ValidationTrustMonitorStatus::Cancelled)
        ->and($validation->status)->toBe($originalStatus)
        ->and($validation->evaluation->decision)->toBe($originalDecision)
        ->and($validation->publicVerificationRecord()->firstOrFail()->snapshot)->toBe($snapshot)
        ->and($organization->id)->toBe($monitor->organization_id)
        ->and($creator->refresh()->isPlatformAdmin())->toBeFalse();
});

it('does not allow terminal validations to be monitored', function () {
    [$validation, $admin] = issuedValidation();
    [, $creator] = trustMonitorCreator($validation);

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Revoked,
        $admin,
        'Material integrity issue confirmed.',
    );

    expect(fn () => app(ValidationTrustMonitoring::class)->configure($validation->refresh(), $creator))
        ->toThrow(ValidationTrustMonitoringException::class);
});
