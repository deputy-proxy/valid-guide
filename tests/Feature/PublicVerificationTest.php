<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\ValidationStatus;
use App\Models\PublicVerificationRecord;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\PublicVerificationPublication;
use App\Services\PublicVerificationReader;
use App\Services\ValidationStateTransition;
use Illuminate\Support\Facades\Route;

it('renders a public verification record from the persisted snapshot', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $response = $this->get(route('public.verify.show', [
        'verificationIdentifier' => $validation->verification_identifier,
    ]));

    $response->assertOk()
        ->assertSee('Test Product 1.0')
        ->assertSee('Test Creator')
        ->assertSee('release-1')
        ->assertSee('VG-TEST-001')
        ->assertSee('Public Auditor')
        ->assertSee('Clear strength')
        ->assertSee('Minor weakness')
        ->assertSee('meta name="robots" content="index,follow"', false)
        ->assertSee(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]), false);
});

it('keeps the verification record stable when internal product data changes', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $validation->productRelease->product->organization->update(['name' => 'Changed Creator Internally']);

    $response = $this->get(route('public.verify.show', [
        'verificationIdentifier' => $validation->verification_identifier,
    ]));

    $response->assertOk()
        ->assertSee('Test Creator')
        ->assertDontSee('Changed Creator Internally');
});

it('renders a revoked verification with its persisted trust history', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Revoked,
        $admin,
        'Revoked after a material integrity issue.',
    );

    $response = $this->get(route('public.verify.show', [
        'verificationIdentifier' => $validation->verification_identifier,
    ]));

    $response->assertOk()
        ->assertSee('This verification has been revoked')
        ->assertSee('Revoked after a material integrity issue.')
        ->assertSee('VG-TEST-001');
});

it('uses a non-disclosing state for unknown verification identifiers', function (): void {
    $response = $this->get(route('public.verify.show', [
        'verificationIdentifier' => 'VG-DOES-NOT-EXIST',
    ]));

    $response->assertOk()
        ->assertSee('Verification record unavailable')
        ->assertDontSee('Test Creator')
        ->assertDontSee('Public Auditor')
        ->assertDontSee('internal');
});

it('uses a deliberate state for malformed verification identifiers submitted to lookup', function (): void {
    $response = $this->get(route('public.verify', [
        'identifier' => 'not/a/valid identifier',
    ]));

    $response->assertOk()
        ->assertSee('Verification record unavailable')
        ->assertSee('Try another identifier');
});

it('does not expose private auditor data from the public snapshot', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $validation->productRelease->product->organization->update(['name' => 'Private Internal Creator Name']);
    $validation->load('evaluation.auditorEvaluations.assignment.auditor');
    $validation->evaluation->auditorEvaluations->first()->assignment->auditor->update([
        'email' => 'private-auditor@example.test',
    ]);

    $response = $this->get(route('public.verify.show', [
        'verificationIdentifier' => $validation->verification_identifier,
    ]));

    $response->assertOk()
        ->assertDontSee('private-auditor@example.test')
        ->assertDontSee('Private Internal Creator Name')
        ->assertSee('Test Creator');
});

it('serves the lookup page without authentication', function (): void {
    $response = $this->get(route('public.verify'));

    $response->assertOk()
        ->assertSee('Verify a Valid.guide record')
        ->assertSee('verification-identifier', false)
        ->assertSee('noindex,follow');

    $route = Route::getRoutes()->getByName('public.verify');

    expect($route)->not->toBeNull();
    expect($route?->gatherMiddleware())->not->toContain('auth');
    expect($route?->gatherMiddleware())->not->toContain('verified');
});

it('persists full report content only when full report visibility is enabled', function (): void {
    $validation = publicVerificationFixture();
    $report = Report::create(['evaluation_id' => $validation->evaluation_id]);
    $version = ReportVersion::create([
        'report_id' => $report->id,
        'version_number' => 1,
        'content_structure' => ['sections' => ['summary' => 'Public report content.']],
        'abstract' => 'Public report abstract.',
        'decision_snapshot' => ['decision' => 'valid'],
        'standard_version_snapshot' => ['version' => '1.0'],
        'created_by' => User::factory()->create()->id,
    ]);
    Report::query()->whereKey($report->id)->update(['current_version_id' => $version->id]);
    $validation->evaluation->refresh();

    $record = app(PublicVerificationPublication::class)->publish($validation);
    expect($record->snapshot['report']['content'])->toBeNull();

    $record->update(['full_report_visible' => true]);
    app(PublicVerificationPublication::class)->sync($validation->refresh());

    $record = $record->refresh();

    expect($record->snapshot['report']['content'])->toBe([
        'sections' => ['summary' => 'Public report content.'],
    ]);
});

it('does not return unpublished public records through the reader', function (): void {
    $validation = publicVerificationFixture();
    PublicVerificationRecord::create([
        'validation_id' => $validation->id,
        'public_slug' => 'vg-unpublished',
        'snapshot' => ['verification_identifier' => 'VG-UNPUBLISHED'],
        'published_at' => null,
    ]);

    expect(app(PublicVerificationReader::class)->read('VG-UNPUBLISHED'))->toBeNull();
});
