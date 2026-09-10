<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProductReleaseStatus;
use App\Enums\ProductType;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ProductReleaseStateTransition;
use Illuminate\Auth\Access\AuthorizationException;

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
        'product_type' => ProductType::Course,
        'status' => 'active',
    ]);
}

function releaseIntegrityRelease(Product $product, ProductReleaseStatus $status = ProductReleaseStatus::Draft): ProductRelease
{
    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'release-'.$product->id.'-'.str()->random(6),
        'title_snapshot' => 'Course',
        'version' => '1.0',
        'status' => ProductReleaseStatus::Draft,
    ]);

    if ($status === ProductReleaseStatus::Current) {
        return app(ProductReleaseStateTransition::class)->publish($release, $product->organization->users()->firstOrFail());
    }

    if ($status === ProductReleaseStatus::Superseded) {
        $release = app(ProductReleaseStateTransition::class)->publish(
            $release,
            $product->organization->users()->firstOrFail(),
        );

        return app(ProductReleaseStateTransition::class)->supersede(
            $release,
            $product->organization->users()->firstOrFail(),
        );
    }

    if ($status === ProductReleaseStatus::Withdrawn) {
        $release = app(ProductReleaseStateTransition::class)->publish(
            $release,
            $product->organization->users()->firstOrFail(),
        );

        return app(ProductReleaseStateTransition::class)->withdraw(
            $release,
            $product->organization->users()->firstOrFail(),
        );
    }

    return $release;
}

it('requires an evaluation request release to belong to its product', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $otherProduct = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Other Course',
        'slug' => 'other-course-'.$organization->id,
        'product_type' => ProductType::Course,
        'status' => 'active',
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

it('prevents changes to every non-draft release', function (ProductReleaseStatus $status) {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product, $status);

    expect(fn () => $release->update(['title_snapshot' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
})->with([
    ProductReleaseStatus::Current,
    ProductReleaseStatus::Withdrawn,
    ProductReleaseStatus::Superseded,
]);

it('prevents deletion of every non-draft release', function (ProductReleaseStatus $status) {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product, $status);

    expect(fn () => $release->delete())
        ->toThrow(DomainStateTransitionException::class);
})->with([
    ProductReleaseStatus::Current,
    ProductReleaseStatus::Withdrawn,
    ProductReleaseStatus::Superseded,
]);

it('publishes a draft release and records the publication timestamp', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product);

    $updated = app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Current, $user);

    expect($updated->status)->toBe(ProductReleaseStatus::Current)
        ->and($updated->published_at)->not->toBeNull();
});

it('supersedes the previous current release when a new release is published', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $first = releaseIntegrityRelease($product);
    $first = app(ProductReleaseStateTransition::class)->publish($first, $user);
    $second = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'second-'.str()->random(8),
        'title_snapshot' => 'Course v2',
        'version' => '2.0',
    ]);

    $second = app(ProductReleaseStateTransition::class)->publish($second, $user);

    expect($first->refresh()->status)->toBe(ProductReleaseStatus::Superseded)
        ->and($second->refresh()->status)->toBe(ProductReleaseStatus::Current)
        ->and(ProductRelease::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReleaseStatus::Current->value)
            ->count())->toBe(1);
});

it('does not allow invalid lifecycle transitions', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product);

    expect(fn () => app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Superseded, $user))
        ->toThrow(DomainStateTransitionException::class);

    $current = app(ProductReleaseStateTransition::class)->publish($release, $user);

    expect(fn () => app(ProductReleaseStateTransition::class)
        ->transition($current, ProductReleaseStatus::Current, $user))
        ->toThrow(DomainStateTransitionException::class);

    $withdrawn = app(ProductReleaseStateTransition::class)->withdraw($current, $user);

    expect(fn () => app(ProductReleaseStateTransition::class)
        ->transition($withdrawn, ProductReleaseStatus::Current, $user))
        ->toThrow(DomainStateTransitionException::class);
});

it('does not allow billing members to change release state', function () {
    $owner = User::factory()->create();
    $billing = User::factory()->create();
    $organization = releaseIntegrityOrganization($owner);
    $organization->users()->attach($billing, ['role' => OrganizationRole::Billing->value]);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product);

    expect(fn () => app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Current, $billing))
        ->toThrow(AuthorizationException::class);
});

it('allows owner admin and editor lifecycle transitions', function (OrganizationRole $role) {
    $actor = User::factory()->create();
    $organization = releaseIntegrityOrganization($actor, $role);
    $product = releaseIntegrityProduct($organization);
    $release = releaseIntegrityRelease($product);

    $release = app(ProductReleaseStateTransition::class)->publish($release, $actor);

    expect($release->status)->toBe(ProductReleaseStatus::Current);
})->with([
    OrganizationRole::Owner,
    OrganizationRole::Admin,
    OrganizationRole::Editor,
]);

it('prevents a release from being moved to another product', function () {
    $user = User::factory()->create();
    $organization = releaseIntegrityOrganization($user);
    $product = releaseIntegrityProduct($organization);
    $otherProduct = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Other Course',
        'slug' => 'other-course-'.str()->random(8),
        'product_type' => ProductType::Course,
        'status' => 'active',
    ]);
    $release = releaseIntegrityRelease($product);

    expect(fn () => $release->update(['product_id' => $otherProduct->id]))
        ->toThrow(DomainStateTransitionException::class);
});
