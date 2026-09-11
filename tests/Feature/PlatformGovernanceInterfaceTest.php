<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function platformAdminForGovernance(): User
{
    $user = User::factory()->create();
    $user->platform_role = PlatformRole::Admin;
    $user->save();

    return $user;
}

it('allows platform administrators to access governance resources', function () {
    $user = platformAdminForGovernance();

    $this->actingAs($user);

    foreach ([
        '/admin/evaluations',
        '/admin/validations',
        '/admin/reports',
        '/admin/clarification-requests',
        '/admin/disputes',
        '/admin/public-verification-records',
        '/admin/audit-logs',
    ] as $uri) {
        $this->get($uri)->assertSuccessful();
    }
});

it('rejects organization users from platform governance resources', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Governance Organization',
        'slug' => 'governance-organization',
        'status' => 'active',
    ]);
    $organization->users()->attach($user, ['role' => 'owner']);

    $this->actingAs($user);

    foreach ([
        '/admin/evaluations',
        '/admin/validations',
        '/admin/reports',
        '/admin/clarification-requests',
        '/admin/disputes',
        '/admin/public-verification-records',
        '/admin/audit-logs',
    ] as $uri) {
        $this->get($uri)->assertForbidden();
    }
});

it('keeps the audit log read-only at the model boundary', function () {
    $user = platformAdminForGovernance();
    $logId = DB::table('audit_logs')->insertGetId([
        'actor_id' => $user->id,
        'event' => 'governance.test',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'before' => json_encode(['status' => 'old'], JSON_THROW_ON_ERROR),
        'after' => json_encode(['status' => 'new'], JSON_THROW_ON_ERROR),
        'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => now(),
    ]);

    $log = AuditLog::query()->findOrFail($logId);

    expect(fn () => $log->update(['event' => 'tampered']))
        ->toThrow(LogicException::class);
});
