<?php

declare(strict_types=1);

use App\Enums\EvaluationComplexity;
use App\Enums\EvaluationRequestStatus;
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
    $organization = Organization::create(['name' => 'Commercial Test', 'slug' => 'commercial-test-'.$user->id, 'status' => 'active']);
    $organization->users()->attach($user, ['role' => 'owner']);
    $product = Product::create(['organization_id' => $organization->id, 'title' => 'Course', 'slug' => 'commercial-course-'.$user->id]);
    $release = ProductRelease::create(['product_id' => $product->id, 'release_identifier' => 'v1-'.$user->id, 'title_snapshot' => 'Course', 'status' => 'draft']);

    return EvaluationRequest::create(['organization_id' => $organization->id, 'product_id' => $product->id, 'product_release_id' => $release->id, 'status' => EvaluationRequestStatus::Draft]);
}

function commercialTermsPackage(): ServicePackage
{
    return ServicePackage::create(['name' => 'Standard Evaluation', 'slug' => 'standard-evaluation-'.uniqid(), 'description' => 'Independent evaluation.', 'product_types' => ['course', 'guide'], 'complexity_levels' => ['standard'], 'price' => 500, 'currency' => 'EUR', 'status' => 'active']);
}

test('snapshots the service package and price on the request', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $updated = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');
    expect($updated->service_package_id)->toBe($package->id)->and($updated->service_package_name_snapshot)->toBe('Standard Evaluation')->and($updated->quoted_price)->toBe('500.00')->and($updated->currency)->toBe('EUR')->and($updated->complexity)->toBe(EvaluationComplexity::Standard);
});

test('does not allow commercial terms to change once payment has started', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $request = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');
    $actor = $request->organization->users()->first();
    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);
    app(EvaluationRequestStateTransition::class)->transition($request->fresh(), EvaluationRequestStatus::Paid, $actor);
    $request->refresh();

    expect(fn () => $request->update(['quoted_price' => 999]))->toThrow(DomainStateTransitionException::class);
});

test('preserves the request price when the catalog package changes', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $request = app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard');
    $actor = $request->organization->users()->first();
    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);
    app(EvaluationRequestStateTransition::class)->transition($request->fresh(), EvaluationRequestStatus::Paid, $actor);
    $package->update(['price' => 750]);
    expect($request->refresh()->quoted_price)->toBe('500.00')->and($request->service_package_terms_snapshot['price'])->toBe('500.00');
});

test('rejects inactive service packages', function () {
    $request = commercialTermsRequest();
    $package = commercialTermsPackage();
    $package->status = 'inactive';
    $package->save();
    expect(fn () => app(EvaluationRequestCommercialTerms::class)->applyPackage($request, $package, 'standard'))->toThrow(DomainStateTransitionException::class);
});
