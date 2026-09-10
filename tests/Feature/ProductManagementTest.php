<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\OrganizationContext;
use App\Services\ProductManagement;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use function Pest\Livewire\livewire;

function issue52ProductOrganization(User $user, OrganizationRole $role, string $slug): Organization
{
    $organization = Organization::query()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'status' => 'active',
    ]);

    $organization->users()->attach($user, ['role' => $role->value]);

    return $organization;
}

function issue52ValidProductAttributes(string $slug = 'valid-course'): array
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
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-a');
    $otherOrganization = Organization::query()->create([
        'name' => 'Creator B',
        'slug' => 'creator-b',
        'status' => 'active',
    ]);
    $context = new OrganizationContext;

    expect($context->resolve($user, $organization->getKey())->is($organization))->toBeTrue();
    expect(fn () => $context->resolve($user, $otherOrganization->getKey()))->toThrow(AuthorizationException::class);
});

it('allows owner admin and editor to create products but denies billing', function (OrganizationRole $role, bool $allowed) {
    $user = User::factory()->create();
    $organization = issue52ProductOrganization($user, $role, 'creator-'.strtolower($role->value));
    $management = app(ProductManagement::class);

    if ($allowed) {
        $product = $management->create($user, $organization, issue52ValidProductAttributes());
        expect($product->organization_id)->toBe($organization->getKey())
            ->and($product->status)->toBe(ProductStatus::Active);
        return;
    }

    expect(fn () => $management->create($user, $organization, issue52ValidProductAttributes()))
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
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-a');
    $otherOrganization = issue52ProductOrganization($otherUser, OrganizationRole::Owner, 'creator-b');
    $context = new OrganizationContext;

    expect(fn () => $context->resolve($user, $otherOrganization->getKey()))->toThrow(AuthorizationException::class);
    expect($context->resolve($user, $organization->getKey())->getKey())->toBe($organization->getKey());
});

it('does not allow a product to change organizations', function () {
    $user = User::factory()->create();
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-a');
    $otherOrganization = Organization::query()->create([
        'name' => 'Creator B',
        'slug' => 'creator-b',
        'status' => 'active',
    ]);
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Course',
        'slug' => 'course',
        'product_type' => ProductType::Course,
    ]);

    $product->organization_id = $otherOrganization->getKey();

    expect(fn () => $product->save())->toThrow(DomainStateTransitionException::class);
});

it('archives products through the controlled lifecycle service', function () {
    $user = User::factory()->create();
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-a');
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Course',
        'slug' => 'course',
        'product_type' => ProductType::Course,
    ]);

    $archived = app(ProductManagement::class)->archive($user, $product);

    expect($archived->status)->toBe(ProductStatus::Archived);
    $archived->title = 'Changed';
    expect(fn () => $archived->save())->toThrow(DomainStateTransitionException::class);
});

it('does not allow historical products to be deleted', function () {
    $user = User::factory()->create();
    $organization = issue52ProductOrganization($user, OrganizationRole::Owner, 'creator-a');
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Course',
        'slug' => 'course',
        'product_type' => ProductType::Course,
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
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-a');

    expect(fn () => app(ProductManagement::class)->create($user, $organization, ['title' => 'Incomplete']))
        ->toThrow(ValidationException::class);
});

it('renders the Filament product list only for the active organization', function () {
    $user = User::factory()->create();
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-ui-a');
    $otherUser = User::factory()->create();
    $otherOrganization = issue52ProductOrganization($otherUser, OrganizationRole::Editor, 'creator-ui-b');

    $product = app(ProductManagement::class)->create($user, $organization, issue52ValidProductAttributes('ui-product'));
    $otherProduct = app(ProductManagement::class)->create($otherUser, $otherOrganization, issue52ValidProductAttributes('other-ui-product'));

    session(['creator.organization_id' => $organization->getKey()]);
    actingAs($user);

    livewire(ListProducts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$product])
        ->assertCanNotSeeTableRecords([$otherProduct]);
});

it('allows an authorized editor to archive through the Filament action', function () {
    $user = User::factory()->create();
    $organization = issue52ProductOrganization($user, OrganizationRole::Editor, 'creator-ui-archive');
    $product = app(ProductManagement::class)->create($user, $organization, issue52ValidProductAttributes('ui-archive'));

    session(['creator.organization_id' => $organization->getKey()]);
    actingAs($user);

    livewire(ListProducts::class)
        ->callAction(TestAction::make('archive')->table($product))
        ->assertNotified();

    expect($product->refresh()->status)->toBe(ProductStatus::Archived);
});

it('denies billing users product management at the server boundary', function () {
    $owner = User::factory()->create();
    $organization = issue52ProductOrganization($owner, OrganizationRole::Owner, 'creator-ui-billing');
    $billing = User::factory()->create();
    $organization->users()->attach($billing, ['role' => OrganizationRole::Billing->value]);
    $product = app(ProductManagement::class)->create($owner, $organization, issue52ValidProductAttributes('billing-product'));

    expect(Gate::forUser($billing)->allows('viewAny', Product::class))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('update', $product))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('archive', $product))->toBeFalse();
});
