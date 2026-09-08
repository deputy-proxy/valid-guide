<?php

declare(strict_types=1);

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit logs can be created', function () {
    $auditLog = AuditLog::query()->create([
        'event' => 'test.created',
        'auditable_type' => 'App\\Models\\User',
        'auditable_id' => 1,
        'metadata' => ['source' => 'test'],
        'created_at' => now(),
    ]);

    expect($auditLog)->toBeInstanceOf(AuditLog::class);
    expect($auditLog->exists)->toBeTrue();
});

test('audit logs cannot be updated', function () {
    $auditLog = AuditLog::query()->create([
        'event' => 'test.created',
        'auditable_type' => 'App\\Models\\User',
        'auditable_id' => 1,
        'created_at' => now(),
    ]);

    expect(fn () => $auditLog->update(['event' => 'test.updated']))
        ->toThrow(LogicException::class, 'Audit logs are immutable.');
});

test('audit logs cannot be deleted', function () {
    $auditLog = AuditLog::query()->create([
        'event' => 'test.created',
        'auditable_type' => 'App\\Models\\User',
        'auditable_id' => 1,
        'created_at' => now(),
    ]);

    expect(fn () => $auditLog->delete())
        ->toThrow(LogicException::class, 'Audit logs cannot be deleted.');

    expect(AuditLog::query()->find($auditLog->id))->not->toBeNull();
});
