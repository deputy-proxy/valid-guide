<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProductReleaseStatus;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ProductReleaseStateTransition;

function releaseIntegrityOrganization(User $user, OrganizationRole $role = OrganizationRole::Owner): Organization
{
    $organization = Organization::create([
        'name' => 'Release Integrity',
        'slug' => 'release-integrity-'.$user->id,
        'status' => 'active',
    ]);

    $organization->users()->attach($user, ['role' => $role->value]);

    return $organization;
}

function releaseIntegrityProduct(Organization $organization): Product
{
    return Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course-'.$organization->id,
    ]);
}

function releaseIntegrityRelease(Product $product, ProductReleaseStatus $status = ProductReleaseStatus::Draft): ProductRelease
{
    return ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'release-'.$product->id,
        'title_snapshot' => 'Course',
        'version' => '1.0',
        'status' => $status,
    ]);
}

it('requires an evaluation request release to belong to its product', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $otherProduct = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Other Course',
        'slug' => 'other-course-'.$organization->id,
    ]);
    $release = releaseIntegrityRelease($otherProduct);

    expect(fn () => EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'service_package' => 'standard',
        'complexity' => 'standard',
        'quoted_price' => 500,
        'currency' => 'EUR',
        'status' => 'draft',
    ]))->toThrow(DomainStateTransitionException::class);
});

it('prevents changes to a non-draft release', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product, ProductReleaseStatus::Available);

    expect(fn () => $release->update(['title_snapshot' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});

it('prevents deletion of a non-draft release', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product, ProductReleaseStatus::Available);

    expect(fn () => $release->delete())
        ->toThrow(DomainStateTransitionException::class);
});

it('publishes a draft release and records the publication timestamp', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product);

    $updated = app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Available, $user);

    expect($updated->status)->toBe(ProductReleaseStatus::Available)
        ->and($updated->published_at)->not->toBeNull();
});

it('does not allow billing members to change release state', function () {
    $owner = User::factory()->create();
    $billing = User::factory()->create();
    $organization = releaseIntegrityOrganization($owner);
    $organization->users()->attach($billing, ['role' => OrganizationRole::Billing->value]);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product);

    expect(fn () => app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Available, $billing))
        ->toThrow(DomainStateTransitionException::class);
});
