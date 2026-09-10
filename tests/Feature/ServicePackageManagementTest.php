<?php

declare(strict_types=1);

use App\Models\ServicePackage;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ServicePackageManagement;
use Illuminate\Auth\Access\AuthorizationException;

function platformAdmin(): User
{
    return User::factory()->create()->forceFill(['platform_role' => 'admin']);
}

function packageAttributes(): array
{
    return [
        'name' => 'Standard Evaluation',
        'slug' => 'standard-evaluation-'.uniqid(),
        'description' => 'Independent evaluation.',
        'product_types' => ['course', 'guide'],
        'complexity_levels' => ['standard'],
        'price_minor' => 50000,
        'currency' => 'EUR',
    ];
}

test('platform administrators can create service packages', function () {
    $admin = platformAdmin();
    $admin->save();

    $package = app(ServicePackageManagement::class)->create($admin, packageAttributes());

    expect($package)->toBeInstanceOf(ServicePackage::class)
        ->and($package->price_minor)->toBe(50000)
        ->and($package->status)->toBe('active');
});

test('non administrators cannot create service packages', function () {
    $user = User::factory()->create();

    expect(fn () => app(ServicePackageManagement::class)->create($user, packageAttributes()))
        ->toThrow(AuthorizationException::class);
});

test('platform administrators can update package pricing', function () {
    $admin = platformAdmin();
    $admin->save();
    $package = app(ServicePackageManagement::class)->create($admin, packageAttributes());

    $updated = app(ServicePackageManagement::class)->update($admin, $package, [
        ...packageAttributes(),
        'slug' => $package->slug,
        'price_minor' => 75000,
    ]);

    expect($updated->price_minor)->toBe(75000);
});

test('package management rejects non positive prices', function () {
    $admin = platformAdmin();
    $admin->save();

    expect(fn () => app(ServicePackageManagement::class)->create($admin, [
        ...packageAttributes(),
        'price_minor' => 0,
    ]))->toThrow(DomainStateTransitionException::class);
});
