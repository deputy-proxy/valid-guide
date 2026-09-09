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
use App\Services\ProductReleaseStateTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function productReleaseLifecycleFixture(): array
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
    [$actor, $release] = productReleaseLifecycleFixture();

    expect(fn () => ProductRelease::create([
        'product_id' => $release->product_id,
        'release_identifier' => '2026-02',
        'title_snapshot' => 'Example Course',
        'status' => ProductReleaseStatus::Available,
    ]))->toThrow(DomainStateTransitionException::class);
});

test('product release lifecycle fields cannot be changed through direct model mutation', function () {
    [$actor, $release] = productReleaseLifecycleFixture();

    app(ProductReleaseStateTransition::class)->transition($release, ProductReleaseStatus::Available, $actor);

    $release->refresh();

    expect(fn () => $release->update(['status' => ProductReleaseStatus::Withdrawn]))
        ->toThrow(DomainStateTransitionException::class);

    $release->refresh();

    expect(fn () => $release->update(['published_at' => now()->addMinute()]))
        ->toThrow(DomainStateTransitionException::class);
});

test('the lifecycle service remains the controlled path for status changes', function () {
    [$actor, $release] = productReleaseLifecycleFixture();

    $published = app(ProductReleaseStateTransition::class)
        ->transition($release, ProductReleaseStatus::Available, $actor);

    expect($published->status)->toBe(ProductReleaseStatus::Available)
        ->and($published->published_at)->not->toBeNull();

    $withdrawn = app(ProductReleaseStateTransition::class)
        ->transition($published, ProductReleaseStatus::Withdrawn, $actor);

    expect($withdrawn->status)->toBe(ProductReleaseStatus::Withdrawn)
        ->and($withdrawn->published_at)->not->toBeNull();
});
