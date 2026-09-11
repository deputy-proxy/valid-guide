<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductSuitability;

it('allows an authorized editor to configure controlled suitability metadata', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create(['name' => 'Creator', 'slug' => 'creator-suitability', 'status' => 'active']);
    $organization->users()->attach($user, ['role' => OrganizationRole::Editor->value]);
    $product = Product::query()->create(['organization_id' => $organization->getKey(), 'title' => 'Course', 'slug' => 'course-suitability', 'product_type' => 'course']);

    $updated = app(ProductSuitability::class)->update($user, $product, [
        'matching_audiences' => [ProductAudience::Professionals->value],
        'matching_goals' => [ProductGoal::ProfessionalDevelopment->value],
    ]);

    expect(json_decode((string) $updated->getRawOriginal('matching_audiences'), true))->toBe([ProductAudience::Professionals->value])
        ->and(json_decode((string) $updated->getRawOriginal('matching_goals'), true))->toBe([ProductGoal::ProfessionalDevelopment->value]);
});

it('rejects a user without product management access', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $organization = Organization::query()->create(['name' => 'Creator', 'slug' => 'creator-suitability-auth', 'status' => 'active']);
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner->value]);
    $product = Product::query()->create(['organization_id' => $organization->getKey(), 'title' => 'Course', 'slug' => 'course-suitability-auth', 'product_type' => 'course']);

    expect(fn () => app(ProductSuitability::class)->update($otherUser, $product, ['matching_audiences' => [ProductAudience::Professionals->value]]))
        ->toThrow('This action is unauthorized.');
});
