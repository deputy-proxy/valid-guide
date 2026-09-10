<?php

declare(strict_types=1);

use App\Enums\EvaluationComplexity;
use App\Enums\EvaluationRequestStatus;
use App\Enums\ProductType;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationRequestCommercialTerms;
use App\Services\EvaluationRequestStateTransition;

function commercialTermsRequest(): EvaluationRequest
{
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Commercial Test',
        'slug' => 'commercial-test-'.$user->id,
        'status' => 'active',
    ]);
    $organization->users()->attach($user, ['role' => 'owner']);
    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Course',
        'slug' => 'commercial-course-'.$user->id,
        'product_type' => ProductType::Course,
    ]);
    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'v1-'.$user->id,
        'title_snapshot' => 'Course',
        'status' => 'draft',
    ]);

    return EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'status' => EvaluationRequestStatus::Draft,
    ]);
}

function commercialTermsPackage(): ServicePackage
{
    return ServicePackage::create([
        'name' => 'Standard Evaluation',
        'slug' => 'standard-evaluation-'.uniqid(),
        'description' => 'Independent evaluation.',
        'product_types' => ['course', 'guide'],
        'complexity_levels' => ['standard'],
        'price_minor' => 50000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);
}

test('snapshots the service package and price on the request', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();

    $updated = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');

    expect($updated->service_package_id)->toBe($package->id)
        ->and($updated->service_package_name_snapshot)->toBe('Standard Evaluation')
        ->and($updated->quoted_amount_minor)->toBe(50000)
        ->and($updated->currency)->toBe('EUR')
        ->and($updated->complexity)->toBe(EvaluationComplexity::Standard)
        ->and($updated->service_package_terms_snapshot['price_minor'])->toBe(50000);
});

test('does not allow commercial terms to change once payment has started', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $request = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');
    $actor = $request->organization->users()->firstOrFail();

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);
    app(EvaluationRequestStateTransition::class)->transition($request->fresh(), EvaluationRequestStatus::Paid, $actor);
    $request->refresh();

    expect(fn () => $request->update(['quoted_amount_minor' => 999]))
        ->toThrow(DomainStateTransitionException::class);
});

test('preserves the request price when the catalog package changes', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $request = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');
    $actor = $request->organization->users()->firstOrFail();

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);
    app(EvaluationRequestStateTransition::class)->transition($request->fresh(), EvaluationRequestStatus::Paid, $actor);
    $package->update(['price_minor' => 75000]);

    expect($request->refresh()->quoted_amount_minor)->toBe(50000)
        ->and($request->service_package_terms_snapshot['price_minor'])->toBe(50000);
});

test('rejects inactive service packages', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $package->status = 'inactive';
    $package->save();

    expect(fn () => app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard'))
        ->toThrow(DomainStateTransitionException::class);
});

test('rejects a product type not supported by the package', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $package->update(['product_types' => ['guide']]);

    expect(fn () => app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard'))
        ->toThrow(DomainStateTransitionException::class);
});

test('rejects a complexity not supported by the package', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $package->update(['complexity_levels' => ['complex']]);

    expect(fn () => app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard'))
        ->toThrow(DomainStateTransitionException::class);
});

test('rejects direct manipulation of the quoted amount', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $request = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');

    expect(fn () => $request->update(['quoted_amount_minor' => 1]))
        ->toThrow(DomainStateTransitionException::class);
});
