<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProductType;
use App\Livewire\Creator\EvaluationRequests\CreateEvaluationRequest;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CreatorEvaluationRequestIntake;
use App\Services\DomainStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function creatorWizardFixture(string $role = 'owner'): array
{
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Creator Organization',
        'slug' => 'creator-organization-'.$user->id,
        'status' => 'active',
    ]);
    $organization->users()->attach($user, ['role' => $role]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Evaluation Course',
        'slug' => 'evaluation-course-'.$user->id,
        'product_type' => ProductType::Course,
        'description' => 'A complete course for testing the creator wizard.',
        'canonical_url' => 'https://example.test/course',
        'target_audience' => 'Professional learners',
        'claimed_outcomes' => ['Apply the method in practice.'],
        'language' => 'en',
        'status' => 'active',
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'release-'.$user->id,
        'title_snapshot' => $product->title,
        'version' => '1.0',
        'status' => 'draft',
    ]);
    DB::table('product_releases')->where('id', $release->id)->update(['status' => 'current']);
    $release->refresh();

    $package = ServicePackage::create([
        'name' => 'Standard Evaluation',
        'slug' => 'standard-evaluation-'.$user->id,
        'description' => 'Creator evaluation package.',
        'product_types' => ['course'],
        'complexity_levels' => ['standard'],
        'price_minor' => 25000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    return [$user, $organization, $product, $release, $package];
}

it('starts one draft request for the creator organization', function () {
    [$user, $organization, $product] = creatorWizardFixture();
    $this->actingAs($user);
    $intake = app(CreatorEvaluationRequestIntake::class);

    $first = $intake->start($user, $organization->id);
    $first = $intake->selectProduct($user, $first, $product);
    $second = $intake->start($user, $organization->id);
    $second = $intake->selectProduct($user, $second, $product);

    expect($second->id)->toBe($first->id)
        ->and(EvaluationRequest::query()->where('organization_id', $organization->id)->count())->toBe(1)
        ->and($first->status)->toBe(EvaluationRequestStatus::Draft);
});

it('completes the creator wizard and reaches the payment handoff', function () {
    [$user, $organization, $product, $release, $package] = creatorWizardFixture();
    $this->actingAs($user);

    Livewire::test(CreateEvaluationRequest::class, ['organizationId' => $organization->id])
        ->set('productId', $product->id)
        ->call('next')
        ->assertSet('currentStep', 2)
        ->set('productReleaseId', $release->id)
        ->set('scope', 'Evaluate the complete learning experience and its practical applicability.')
        ->set('completionWindow', '10 business days')
        ->call('next')
        ->assertSet('currentStep', 3)
        ->set('materialType', 'url')
        ->set('materialLabel', 'Course landing page')
        ->set('materialLocation', 'https://example.test/course')
        ->call('next')
        ->assertSet('currentStep', 4)
        ->set('claimsConfirmed', true)
        ->set('audienceConfirmed', true)
        ->call('next')
        ->assertSet('currentStep', 5)
        ->set('servicePackageId', $package->id)
        ->set('complexity', 'standard')
        ->call('submitForPayment')
        ->assertSet('currentStep', 6);

    $request = EvaluationRequest::query()->where('organization_id', $organization->id)->firstOrFail();

    expect($request->status)->toBe(EvaluationRequestStatus::AwaitingPayment)
        ->and($request->product_id)->toBe($product->id)
        ->and($request->product_release_id)->toBe($release->id)
        ->and($request->quoted_amount_minor)->toBe(25000)
        ->and($request->currency)->toBe('EUR')
        ->and($request->materials()->count())->toBe(1);
});

it('resumes an existing draft without creating another request', function () {
    [$user, $organization, $product] = creatorWizardFixture();
    $this->actingAs($user);
    $request = app(CreatorEvaluationRequestIntake::class)->start($user, $organization->id);
    $request = app(CreatorEvaluationRequestIntake::class)->selectProduct($user, $request, $product);

    Livewire::test(CreateEvaluationRequest::class, [
        'organizationId' => $organization->id,
        'evaluationRequestId' => $request->id,
    ])
        ->assertSet('productId', $product->id)
        ->assertSet('currentStep', 1);

    expect(EvaluationRequest::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

it('preserves state while navigating backwards', function () {
    [$user, $organization, $product, $release] = creatorWizardFixture();
    $this->actingAs($user);

    Livewire::test(CreateEvaluationRequest::class, ['organizationId' => $organization->id])
        ->set('productId', $product->id)
        ->call('next')
        ->set('productReleaseId', $release->id)
        ->set('scope', 'Evaluate the published learning experience.')
        ->call('next')
        ->assertSet('currentStep', 3)
        ->call('back')
        ->assertSet('currentStep', 2)
        ->assertSet('productReleaseId', $release->id)
        ->assertSet('scope', 'Evaluate the published learning experience.');
});

it('rejects cross-tenant products and releases server-side', function () {
    [$user, $organization, $product] = creatorWizardFixture();
    [, , , $otherRelease] = creatorWizardFixture();
    $this->actingAs($user);

    Livewire::test(CreateEvaluationRequest::class, ['organizationId' => $organization->id])
        ->set('productId', $otherRelease->product_id)
        ->call('next')
        ->assertHasErrors('form');

    $request = EvaluationRequest::query()->where('organization_id', $organization->id)->firstOrFail();
    $validRequest = app(CreatorEvaluationRequestIntake::class)->selectProduct($user, $request, $product);

    expect(fn () => app(CreatorEvaluationRequestIntake::class)->selectRelease($user, $validRequest, $otherRelease))
        ->toThrow(DomainStateTransitionException::class);
});

it('denies the billing role at the creator intake authorization boundary', function () {
    [$user, $organization] = creatorWizardFixture(OrganizationRole::Billing->value);
    $this->actingAs($user);

    expect(fn () => app(CreatorEvaluationRequestIntake::class)->start($user, $organization->id))
        ->toThrow(AuthorizationException::class);
});

it('does not expose internal auditor information in the creator response', function () {
    [$user, $organization, $product] = creatorWizardFixture();
    $this->actingAs($user);

    Livewire::test(CreateEvaluationRequest::class, ['organizationId' => $organization->id])
        ->set('productId', $product->id)
        ->call('next')
        ->assertDontSee('auditor_id')
        ->assertDontSee('deliberation')
        ->assertDontSee('private evidence');
});

it('does not reach payment with incomplete intake', function () {
    [$user, $organization, $product, $release, $package] = creatorWizardFixture();
    $this->actingAs($user);

    $component = Livewire::test(CreateEvaluationRequest::class, ['organizationId' => $organization->id])
        ->set('productId', $product->id)
        ->call('next')
        ->set('productReleaseId', $release->id)
        ->set('scope', 'Evaluate the product.')
        ->call('next');

    $component
        ->set('currentStep', 5)
        ->set('servicePackageId', $package->id)
        ->set('complexity', 'standard')
        ->call('submitForPayment')
        ->assertSet('currentStep', 5)
        ->assertHasErrors('form');

    expect(EvaluationRequest::query()->where('organization_id', $organization->id)->firstOrFail()->status)
        ->toBe(EvaluationRequestStatus::Draft);
});
