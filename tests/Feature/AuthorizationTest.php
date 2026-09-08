<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('scopes creator access to organization membership', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Learning',
        'slug' => 'acme-learning',
        'status' => 'active',
    ]);

    $organization->users()->attach($member, ['role' => OrganizationRole::Editor->value]);

    expect(Gate::forUser($member)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('view', $organization))->toBeFalse();
});

it('allows editors to manage products but not organizations', function () {
    $editor = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Learning',
        'slug' => 'acme-learning',
        'status' => 'active',
    ]);
    $organization->users()->attach($editor, ['role' => OrganizationRole::Editor->value]);

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
    $organization = Organization::create([
        'name' => 'Acme Learning',
        'slug' => 'acme-learning',
        'status' => 'active',
    ]);
    $organization->users()->attach($editor, ['role' => OrganizationRole::Editor->value]);
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
    $organization = Organization::create([
        'name' => 'Acme Learning',
        'slug' => 'acme-learning',
        'status' => 'active',
    ]);
    $organization->users()->attach($member, ['role' => OrganizationRole::Editor->value]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'course',
    ]);
    $release = ProductRelease::create([
        'product_id' => $product->id,
        'version' => '1.0',
        'status' => 'draft',
    ]);

    expect(Gate::forUser($member)->allows('view', $release))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('view', $release))->toBeFalse();
});
