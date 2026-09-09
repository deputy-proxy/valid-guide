<?php

declare(strict_types=1);

use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationMaterial;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationMaterialIntake;

function materialIntakeRequest(): EvaluationRequest
{
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Material Test',
        'slug' => 'material-test-'.$user->id,
        'status' => 'active',
    ]);
    $organization->users()->attach($user, ['role' => 'owner']);
    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'material-course-'.$user->id,
    ]);
    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'v1-'.$user->id,
        'title_snapshot' => 'Course',
        'status' => 'draft',
    ]);

    return EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'status' => EvaluationRequestStatus::Paid,
    ]);
}

it('records submitted material with its provenance', function () {
    $request = materialIntakeRequest();
    $user = $request->organization->users()->first();

    $material = app(EvaluationMaterialIntake::class)->submit(
        $request,
        $user,
        EvaluationMaterialType::Url,
        'Course landing page',
        'Public course page used for intake.',
        'https://example.test/course',
        ['source' => 'creator'],
    );

    expect($material)->toBeInstanceOf(EvaluationMaterial::class)
        ->and($material->evaluation_request_id)->toBe($request->id)
        ->and($material->submitted_by)->toBe($user->id)
        ->and($material->status)->toBe('submitted')
        ->and($material->submitted_at)->not->toBeNull();
});

it('requires a location for externally accessed materials', function () {
    $request = materialIntakeRequest();
    $user = $request->organization->users()->first();

    expect(fn () => app(EvaluationMaterialIntake::class)->submit(
        $request,
        $user,
        EvaluationMaterialType::Access,
        'Learning platform access',
    ))->toThrow(DomainStateTransitionException::class);
});

it('prevents unauthorized members from submitting material', function () {
    $request = materialIntakeRequest();
    $user = User::factory()->create();

    expect(fn () => app(EvaluationMaterialIntake::class)->submit(
        $request,
        $user,
        EvaluationMaterialType::File,
        'Course workbook',
        null,
        'storage://workbook.pdf',
    ))->toThrow(DomainStateTransitionException::class);
});

it('allows only platform administrators to verify material', function () {
    $request = materialIntakeRequest();
    $user = $request->organization->users()->first();
    $material = app(EvaluationMaterialIntake::class)->submit(
        $request,
        $user,
        EvaluationMaterialType::Url,
        'Course page',
        null,
        'https://example.test/course',
    );
    $nonAdmin = User::factory()->create();

    expect(fn () => app(EvaluationMaterialIntake::class)->verify($material, $nonAdmin, 'Checked access.'))
        ->toThrow(DomainStateTransitionException::class);
});

it('verifies material and then makes its identity immutable', function () {
    $request = materialIntakeRequest();
    $user = $request->organization->users()->first();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $material = app(EvaluationMaterialIntake::class)->submit(
        $request,
        $user,
        EvaluationMaterialType::Url,
        'Course page',
        null,
        'https://example.test/course',
    );

    $verified = app(EvaluationMaterialIntake::class)->verify($material, $admin, 'Access checked and material confirmed.');

    expect($verified->status)->toBe('verified')
        ->and($verified->verified_by)->toBe($admin->id)
        ->and($verified->verified_at)->not->toBeNull();

    expect(fn () => $verified->update(['location' => 'https://example.test/changed']))
        ->toThrow(DomainStateTransitionException::class);
});

it('does not accept material after intake is ready', function () {
    $request = materialIntakeRequest();
    $request->status = EvaluationRequestStatus::Ready;
    $request->save();
    $user = $request->organization->users()->first();

    expect(fn () => app(EvaluationMaterialIntake::class)->submit(
        $request,
        $user,
        EvaluationMaterialType::Url,
        'Late material',
        null,
        'https://example.test/late',
    ))->toThrow(DomainStateTransitionException::class);
});
