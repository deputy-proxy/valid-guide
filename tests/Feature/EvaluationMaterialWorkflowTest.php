<?php

declare(strict_types=1);

use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\PlatformRole;
use App\Models\EvaluationMaterial;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationMaterialSubmission;
use App\Services\EvaluationMaterialVerification;
use App\Services\EvaluationRequestStateTransition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function materialWorkflowRequest(User $member): EvaluationRequest
{
    $organization = Organization::create([
        'name' => 'Material Organization',
        'slug' => 'material-'.uniqid(),
        'status' => 'active',
    ]);
    $organization->users()->attach($member, ['role' => OrganizationRole::Editor->value]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Material Course',
        'slug' => 'material-course-'.uniqid(),
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'material-release-1',
        'title_snapshot' => 'Material Course',
        'version' => '1.0',
        'status' => 'current',
    ]);

    $package = ServicePackage::create([
        'name' => 'Material Evaluation',
        'slug' => 'material-package-'.uniqid(),
        'description' => 'Evaluation package.',
        'product_types' => ['course'],
        'complexity_levels' => ['standard'],
        'price' => 100,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    $request = EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'service_package_id' => $package->id,
        'service_package' => $package->slug,
        'service_package_name_snapshot' => $package->name,
        'service_package_description_snapshot' => $package->description,
        'service_package_terms_snapshot' => [
            'name' => $package->name,
            'description' => $package->description,
        ],
        'complexity' => 'standard',
        'quoted_price' => 100,
        'currency' => 'EUR',
        'status' => EvaluationRequestStatus::Draft,
    ]);

    $transition = app(EvaluationRequestStateTransition::class);
    $transition->transition($request, EvaluationRequestStatus::AwaitingPayment, $member);
    $transition->transition($request, EvaluationRequestStatus::Paid, $member);

    return $request->fresh();
}

it('submits all supported material types with server provenance', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);
    $service = app(EvaluationMaterialSubmission::class);

    $file = $service->submit($member, $request, EvaluationMaterialType::File, 'Workbook', location: 'private/workbook.pdf');
    $url = $service->submit($member, $request, EvaluationMaterialType::Url, 'Course URL', location: 'https://example.test/course');
    $access = $service->submit($member, $request, EvaluationMaterialType::Access, 'Admin access', location: 'https://example.test/login');
    $note = $service->submit($member, $request, EvaluationMaterialType::Note, 'Important note');

    expect([$file, $url, $access, $note])->toHaveCount(4);
    expect($file->submitted_by)->toBe($member->getKey())
        ->and($file->submitted_at)->not->toBeNull()
        ->and($file->status)->toBe('submitted');

    expect(DB::table('audit_logs')
        ->where('event', 'evaluation_material.submitted')
        ->where('auditable_type', EvaluationMaterial::class)
        ->where('actor_id', $member->getKey())
        ->count())->toBe(4);
});

it('rejects external material without a location', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);

    expect(fn () => app(EvaluationMaterialSubmission::class)->submit(
        $member,
        $request,
        EvaluationMaterialType::Url,
        'Course URL',
    ))->toThrow(DomainStateTransitionException::class);
});

it('rejects material submission outside the paid intake states', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);
    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::Intake, $member);
    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingCreator, $member);
    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::Ready, $member);

    expect(fn () => app(EvaluationMaterialSubmission::class)->submit(
        $member,
        $request,
        EvaluationMaterialType::Note,
        'Late note',
    ))->toThrow(DomainStateTransitionException::class);
});

it('rejects cross-tenant material submission', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $request = materialWorkflowRequest($member);

    expect(fn () => app(EvaluationMaterialSubmission::class)->submit(
        $outsider,
        $request,
        EvaluationMaterialType::Note,
        'Outsider note',
    ))->toThrow(AuthorizationException::class);
});

it('allows only platform administrators to verify materials', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);
    $material = app(EvaluationMaterialSubmission::class)->submit(
        $member,
        $request,
        EvaluationMaterialType::Note,
        'Evidence note',
    );
    $organizationAdmin = User::factory()->create();
    $request->organization->users()->attach($organizationAdmin, ['role' => OrganizationRole::Admin->value]);

    expect(fn () => app(EvaluationMaterialVerification::class)->verify($organizationAdmin, $material))
        ->toThrow(AuthorizationException::class);

    $platformAdmin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    $verified = app(EvaluationMaterialVerification::class)->verify($platformAdmin, $material, 'Checked and accessible.');

    expect($verified->status)->toBe('verified')
        ->and($verified->verified_by)->toBe($platformAdmin->getKey())
        ->and($verified->verified_at)->not->toBeNull()
        ->and($verified->verification_notes)->toBe('Checked and accessible.');
});

it('prevents re-verification and content mutation after submission', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);
    $material = app(EvaluationMaterialSubmission::class)->submit(
        $member,
        $request,
        EvaluationMaterialType::Url,
        'Course URL',
        location: 'https://example.test/course',
    );
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    app(EvaluationMaterialVerification::class)->verify($admin, $material);

    expect(fn () => $material->update(['label' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class)
        ->and(fn () => $material->delete())
        ->toThrow(DomainStateTransitionException::class)
        ->and(fn () => app(EvaluationMaterialVerification::class)->verify($admin, $material))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires verified evidence before readiness and closes the evidence set', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);
    $transition = app(EvaluationRequestStateTransition::class);
    $transition->transition($request, EvaluationRequestStatus::Intake, $member);

    $material = app(EvaluationMaterialSubmission::class)->submit(
        $member,
        $request,
        EvaluationMaterialType::Note,
        'Evidence note',
    );

    expect(fn () => $transition->transition($request, EvaluationRequestStatus::Ready, $member))
        ->toThrow(DomainStateTransitionException::class);

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    app(EvaluationMaterialVerification::class)->verify($admin, $material);

    $ready = $transition->transition($request, EvaluationRequestStatus::Ready, $member);
    expect($ready->status)->toBe(EvaluationRequestStatus::Ready);

    expect(fn () => app(EvaluationMaterialSubmission::class)->submit(
        $member,
        $ready,
        EvaluationMaterialType::Note,
        'Late evidence',
    ))->toThrow(DomainStateTransitionException::class);
});

it('blocks direct creation unless the material has submission provenance and an allowed request state', function () {
    $member = User::factory()->create();
    $request = materialWorkflowRequest($member);

    expect(fn () => EvaluationMaterial::create([
        'evaluation_request_id' => $request->getKey(),
        'type' => EvaluationMaterialType::Note,
        'label' => 'Missing provenance',
        'status' => 'submitted',
    ]))->toThrow(DomainStateTransitionException::class);
});
