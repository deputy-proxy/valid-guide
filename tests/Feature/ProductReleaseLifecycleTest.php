<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProductReleaseStatus;
use App\Enums\ProductType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ProductReleaseManagement;
use App\Services\ProductReleaseStateTransition;
use App\Filament\Resources\ProductReleases\Pages\ListProductReleases;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

function productReleaseLifecycleFixtureForTransition(): array
{
    $actor = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Example Publisher',
        'slug' => 'example-publisher-'.str()->random(8),
        'status' => 'active',
    ]);

    $organization->users()->attach($actor, ['role' => OrganizationRole::Owner->value]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Example Course',
        'slug' => 'example-course-'.str()->random(8),
        'product_type' => ProductType::Course,
        'status' => 'active',
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => '2026-01',
        'title_snapshot' => $product->title,
    ]);

    return [$actor, $release];
}

test('a product release must be created as a draft', function () {
    [$actor, $release] = productReleaseLifecycleFixtureForTransition();

    expect(fn () => ProductRelease::create([
        'product_id' => $release->product_id,
        'release_identifier' => '2026-02',
        'title_snapshot' => 'Example Course',
        'status' => ProductReleaseStatus::Current,
    ]))->toThrow(DomainStateTransitionException::class);
});

test('product release lifecycle fields cannot be changed through direct model mutation', function () {
    [$actor, $release] = productReleaseLifecycleFixtureForTransition();

    app(ProductReleaseStateTransition::class)->transition($release, ProductReleaseStatus::Current, $actor);

    $release->refresh();

    expect(fn () => $release->update(['status' => ProductReleaseStatus::Withdrawn]))
        ->toThrow(DomainStateTransitionException::class);

    $release->refresh();

    expect(fn () => $release->update(['published_at' => now()->addMinute()]))
        ->toThrow(DomainStateTransitionException::class);
});

test('the lifecycle service remains the controlled path for status changes', function () {
    [$actor, $release] = productReleaseLifecycleFixtureForTransition();

    $published = app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Current, $actor);

    expect($published->status)->toBe(ProductReleaseStatus::Current)
        ->and($published->published_at)->not->toBeNull();

    $withdrawn = app(ProductReleaseStateTransition::class)
        ->transition($published, ProductReleaseStatus::Withdrawn, $actor);

    expect($withdrawn->status)->toBe(ProductReleaseStatus::Withdrawn)
        ->and($withdrawn->published_at)->not->toBeNull();
});

test('the Filament release list is scoped to the active organization', function () {
    [$user, $release] = productReleaseLifecycleFixtureForTransition();
    [$otherUser, $otherRelease] = productReleaseLifecycleFixtureForTransition();

    session(['creator.organization_id' => $release->product->organization_id]);
    actingAs($user);

    livewire(ListProductReleases::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$release])
        ->assertCanNotSeeTableRecords([$otherRelease]);

    expect($otherUser->id)->not->toBe($user->id);
});

test('draft releases expose only draft actions', function () {
    [$user, $release] = productReleaseLifecycleFixtureForTransition();

    session(['creator.organization_id' => $release->product->organization_id]);
    actingAs($user);

    livewire(ListProductReleases::class)
        ->assertActionVisible(TestAction::make('publish')->table($release))
        ->assertActionVisible(TestAction::make('edit')->table($release))
        ->assertActionHidden(TestAction::make('supersede')->table($release))
        ->assertActionHidden(TestAction::make('withdraw')->table($release));
});

test('non-draft releases cannot be ordinarily edited and expose only valid transitions', function () {
    [$user, $release] = productReleaseLifecycleFixtureForTransition();
    app(ProductReleaseStateTransition::class)->publish($release, $user);
    $release->refresh();

    session(['creator.organization_id' => $release->product->organization_id]);
    actingAs($user);

    livewire(ListProductReleases::class)
        ->assertActionHidden(TestAction::make('edit')->table($release))
        ->assertActionHidden(TestAction::make('publish')->table($release))
        ->assertActionVisible(TestAction::make('supersede')->table($release))
        ->assertActionVisible(TestAction::make('withdraw')->table($release));
});

test('publishing a second release preserves the historical identity of the first', function () {
    [$user, $firstRelease] = productReleaseLifecycleFixtureForTransition();
    $product = $firstRelease->product;
    app(ProductReleaseStateTransition::class)->publish($firstRelease, $user);

    $secondRelease = app(ProductReleaseManagement::class)->create($user, $product, [
        'release_identifier' => '2026-02',
        'title_snapshot' => 'Example Course',
        'version' => '2.0',
    ]);

    app(ProductReleaseStateTransition::class)->publish($secondRelease, $user);

    expect($firstRelease->refresh()->status)->toBe(ProductReleaseStatus::Superseded)
        ->and($firstRelease->release_identifier)->toBe('2026-01')
        ->and($secondRelease->refresh()->status)->toBe(ProductReleaseStatus::Current)
        ->and($secondRelease->release_identifier)->toBe('2026-02');
});

test('billing users cannot manage product releases', function () {
    [$owner, $release] = productReleaseLifecycleFixtureForTransition();
    $organization = $release->product->organization;
    $billing = User::factory()->create();
    $organization->users()->attach($billing, ['role' => OrganizationRole::Billing->value]);

    expect(Gate::forUser($billing)->allows('view', $release))->toBeFalse()
        ->and(Gate::forUser($billing)->allows('publish', $release))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('publish', $release))->toBeTrue();
});

test('a forged product release update cannot cross organization boundaries', function () {
    [$user, $release] = productReleaseLifecycleFixtureForTransition();
    [$otherUser, $otherRelease] = productReleaseLifecycleFixtureForTransition();

    expect(Gate::forUser($user)->allows('update', $otherRelease))->toBeFalse();

    expect(fn () => app(ProductReleaseManagement::class)->update($user, $otherRelease, [
        'release_identifier' => $otherRelease->release_identifier,
        'title_snapshot' => 'Forged release',
    ]))->toThrow(AuthorizationException::class);

    expect($otherUser->id)->not->toBe($user->id);
});
