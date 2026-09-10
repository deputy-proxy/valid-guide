<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\OrganizationContext;
use App\Services\ProductManagement;
use Illuminate\Auth\Access\AuthorizationException;

function productManagementOrganization(User $user, OrganizationRole $role, string $slug): Organization
{
    $organization = Organization::query()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'status' => 'active',
    ]);

    $organization->users()->attach($user, ['role' => $role->value]);

    return $organization;
}

function validProductAttributes(string $slug = 'valid-course'): array
{
    return [
        'title' => 'Valid Course',
        'slug' => $slug,
        'product_type' => ProductType::Course->value,
        'description' => 'A complete course description.',
        'canonical_url' => 'https://example.com/courses/valid-course',
        'target_audience' => 'Adult learners',
        'claimed_outcomes' => ['Learners can apply the method.'],
        'language' => 'en',
    ];
}

it('resolves only organizations the user belongs to', function () {
    $user = User::factory()->create();
    $organization = productManagementOrganization($user, OrganizationRole::Editor, 'creator-a');
    $otherOrganization = Organization::query()->create([
        'name' => 'Creator B',
        'slug' => 'creator-b',
        'status' => 'active',
    ]);

    $context = new OrganizationContext();

    expect($context->resolve($user, $organization->getKey())->is($organization))->toBeTrue();

    expect(fn () => $context->resolve($user, $otherOrganization->getKey()))
        ->toThrow(AuthorizationException::class);
});

it('allows owner admin and editor to create products but denies billing', function (OrganizationRole $role, bool $allowed) {
    $user = User::factory()->create();
    $organization = productManagementOrganization($user, $role, 'creator-'.strtolower($role->value));
    $management = new ProductManagement();

    if ($allowed) {
        $product = $management->create($user, $organization, validProductAttributes());

        expect($product->organization_id)->toBe($organization->getKey())
            ->and($product->status)->toBe(ProductStatus::Active);

        return;
    }

    expect(fn () => $management->create($user, $organization, validProductAttributes()))
        ->toThrow(AuthorizationException::class);
})->with([
    [OrganizationRole::Owner, true],
    [OrganizationRole::Admin, true],
    [OrganizationRole::Editor, true],
    [OrganizationRole::Billing, false],
]);

it('rejects a forged organization context even when the identifier is supplied manually', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $organization = productManagementOrganization($user, OrganizationRole::Editor, 'creator-a');
    $otherOrganization = productManagementOrganization($otherUser, OrganizationRole::Owner, 'creator-b');

    expect(fn () => (new OrganizationContext())->resolve($user, $otherOrganization->getKey()))
        ->toThrow(AuthorizationException::class);

    expect((new OrganizationContext())->resolve($user, $organization->getKey())->getKey())
        ->toBe($organization->getKey());
});

it('does not allow a product to change organizations', function () {
    $user = User::factory()->create();
    $organization = productManagementOrganization($user, OrganizationRole::Editor, 'creator-a');
    $otherOrganization = Organization::query()->create([
        'name' => 'Creator B',
        'slug' => 'creator-b',
        'status' => 'active',
    ]);
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Course',
        'slug' => 'course',
    ]);

    $product->organization_id = $otherOrganization->getKey();

    expect(fn () => $product->save())->toThrow(DomainStateTransitionException::class);
});

it('archives products through the controlled lifecycle service', function () {
    $user = User::factory()->create();
    $organization = productManagementOrganization($user, OrganizationRole::Editor, 'creator-a');
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Course',
        'slug' => 'course',
    ]);

    $archived = (new ProductManagement())->archive($user, $product);

    expect($archived->status)->toBe(ProductStatus::Archived);

    $archived->title = 'Changed';

    expect(fn () => $archived->save())->toThrow(DomainStateTransitionException::class);
});

it('does not allow historical products to be deleted', function () {
    $user = User::factory()->create();
    $organization = productManagementOrganization($user, OrganizationRole::Owner, 'creator-a');
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Course',
        'slug' => 'course',
    ]);

    ProductRelease::query()->create([
        'product_id' => $product->getKey(),
        'release_identifier' => 'v1',
        'title_snapshot' => 'Course',
        'status' => 'draft',
    ]);

    expect(fn () => $product->delete())->toThrow(DomainStateTransitionException::class);
});

it('rejects incomplete product data at the application boundary', function () {
    $user = User::factory()->create();
    $organization = productManagementOrganization($user, OrganizationRole::Editor, 'creator-a');

    expect(fn () => (new ProductManagement())->create($user, $organization, [
        'title' => 'Incomplete',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});
