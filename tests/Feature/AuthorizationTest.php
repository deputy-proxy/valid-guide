<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function authorizationOrganization(string $slug, User $user, OrganizationRole $role): Organization
{
    $organization = Organization::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'status' => 'active',
    ]);

    $organization->users()->attach($user, ['role' => $role->value]);

    return $organization;
}

it('scopes creator access to organization membership', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $organization = authorizationOrganization('acme-learning', $member, OrganizationRole::Editor);

    expect(Gate::forUser($member)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('view', $organization))->toBeFalse();
});

it('allows editors to manage products but not organizations', function () {
    $editor = User::factory()->create();
    $organization = authorizationOrganization('acme-learning', $editor, OrganizationRole::Editor);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);

    expect(Gate::forUser($editor)->allows('update', $product))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('update', $organization))->toBeFalse();
});

it('restricts product deletion to owners and admins', function () {
    $editor = User::factory()->create();
    $admin = User::factory()->create();
    $organization = authorizationOrganization('acme-learning', $editor, OrganizationRole::Editor);
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin->value]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);

    expect(Gate::forUser($editor)->allows('delete', $product))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $product))->toBeTrue();
});

it('keeps product releases inside the product organization boundary', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $organization = authorizationOrganization('acme-learning', $member, OrganizationRole::Editor);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);
    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'v1.0',
        'title_snapshot' => 'Course',
        'version' => '1.0',
        'status' => 'draft',
    ]);

    expect(Gate::forUser($member)->allows('view', $release))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('view', $release))->toBeFalse();
});

it('requires product creation to be scoped to the target organization', function () {
    $editor = User::factory()->create();
    $targetOwner = User::factory()->create();
    $otherOrganization = authorizationOrganization('other-learning', $editor, OrganizationRole::Editor);
    $targetOrganization = authorizationOrganization('target-learning', $targetOwner, OrganizationRole::Owner);

    expect(Gate::forUser($editor)->allows('create', [Product::class, $otherOrganization]))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('create', [Product::class, $targetOrganization]))->toBeFalse();
});

it('does not let billing members create products', function () {
    $billing = User::factory()->create();
    $organization = authorizationOrganization('billing-learning', $billing, OrganizationRole::Billing);

    expect(Gate::forUser($billing)->allows('create', [Product::class, $organization]))->toBeFalse();
});

it('requires product release creation to use a product from the member organization', function () {
    $editor = User::factory()->create();
    $otherOwner = User::factory()->create();
    $organization = authorizationOrganization('acme-learning', $editor, OrganizationRole::Editor);
    $otherOrganization = authorizationOrganization('other-learning', $otherOwner, OrganizationRole::Owner);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);
    $otherProduct = Product::create([
        'organization_id' => $otherOrganization->id,
        'title' => 'Other Course',
        'slug' => 'other-course',
    ]);

    expect(Gate::forUser($editor)->allows('create', [ProductRelease::class, $product]))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('create', [ProductRelease::class, $otherProduct]))->toBeFalse();
});

it('does not let billing members create product releases', function () {
    $billing = User::factory()->create();
    $organization = authorizationOrganization('billing-learning', $billing, OrganizationRole::Billing);
    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);

    expect(Gate::forUser($billing)->allows('create', [ProductRelease::class, $product]))->toBeFalse();
});

it('requires evaluation request creation to be scoped to the target organization', function () {
    $editor = User::factory()->create();
    $targetOwner = User::factory()->create();
    $otherOrganization = authorizationOrganization('other-learning', $editor, OrganizationRole::Editor);
    $targetOrganization = authorizationOrganization('target-learning', $targetOwner, OrganizationRole::Owner);

    expect(Gate::forUser($editor)->allows('create', [EvaluationRequest::class, $otherOrganization]))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('create', [EvaluationRequest::class, $targetOrganization]))->toBeFalse();
});

it('does not let billing members create evaluation requests', function () {
    $billing = User::factory()->create();
    $organization = authorizationOrganization('billing-learning', $billing, OrganizationRole::Billing);

    expect(Gate::forUser($billing)->allows('create', [EvaluationRequest::class, $organization]))->toBeFalse();
});

it('enforces the complete product role matrix', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $editor = User::factory()->create();
    $billing = User::factory()->create();
    $organization = authorizationOrganization('matrix-learning', $owner, OrganizationRole::Owner);
    $organization->users()->attach([
        $admin->getKey() => ['role' => OrganizationRole::Admin->value],
        $editor->getKey() => ['role' => OrganizationRole::Editor->value],
        $billing->getKey() => ['role' => OrganizationRole::Billing->value],
    ]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);

    foreach ([$owner, $admin, $editor] as $creator) {
        expect(Gate::forUser($creator)->allows('view', $product))->toBeTrue()
            ->and(Gate::forUser($creator)->allows('update', $product))->toBeTrue()
            ->and(Gate::forUser($creator)->allows('archive', $product))->toBeTrue();
    }

    expect(Gate::forUser($owner)->allows('delete', $product))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $product))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('delete', $product))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('view', $product))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('update', $product))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('archive', $product))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('delete', $product))->toBeFalse();
});
