<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\ProductType;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ServicePackage;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationRequestStateTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function evaluationRequestLifecycleFixture(): EvaluationRequest
{
    $organization = Organization::create([
        'name' => 'Example Publisher',
        'slug' => 'example-publisher',
        'status' => 'active',
    ]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Example Course',
        'slug' => 'example-course',
        'product_type' => ProductType::Course,
        'status' => 'active',
    ]);

    $package = ServicePackage::create([
        'name' => 'Standard Validation',
        'slug' => 'standard-validation',
        'description' => 'Standard validation package',
        'price' => 500,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    return EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'service_package_id' => $package->id,
        'service_package' => $package->slug,
        'service_package_name_snapshot' => $package->name,
        'service_package_description_snapshot' => $package->description,
        'complexity' => 'standard',
        'quoted_price' => $package->price,
        'currency' => $package->currency,
    ]);
}

test('evaluation request status cannot be changed directly', function () {
    $request = evaluationRequestLifecycleFixture();
    $request->status = EvaluationRequestStatus::AwaitingPayment;

    expect(fn () => $request->save())
        ->toThrow(DomainStateTransitionException::class);
});

test('evaluation request lifecycle timestamps cannot be changed directly', function () {
    $request = evaluationRequestLifecycleFixture();
    $request->submitted_at = now();

    expect(fn () => $request->save())
        ->toThrow(DomainStateTransitionException::class);
});

test('state transition service controls lifecycle timestamps and submission time', function () {
    $request = evaluationRequestLifecycleFixture();

    app(EvaluationRequestStateTransition::class)
        ->transition($request, EvaluationRequestStatus::AwaitingPayment);

    $request->refresh();

    expect($request->status)->toBe(EvaluationRequestStatus::AwaitingPayment)
        ->and($request->submitted_at)->not->toBeNull()
        ->and($request->payment_started_at)->not->toBeNull();
});

test('state transition service rechecks the locked current state', function () {
    $request = evaluationRequestLifecycleFixture();

    $request->status = EvaluationRequestStatus::Cancelled;
    Illuminate\Support\Facades\DB::table('evaluation_requests')
        ->where('id', $request->id)
        ->update(['status' => EvaluationRequestStatus::Cancelled->value]);

    expect(fn () => app(EvaluationRequestStateTransition::class)
        ->transition($request, EvaluationRequestStatus::AwaitingPayment))
        ->toThrow(DomainStateTransitionException::class);
});

test('commercial terms remain immutable after payment processing starts', function () {
    $request = evaluationRequestLifecycleFixture();
    app(EvaluationRequestStateTransition::class)
        ->transition($request, EvaluationRequestStatus::AwaitingPayment);

    $request->quoted_price = 1;

    expect(fn () => $request->save())
        ->toThrow(DomainStateTransitionException::class);
});
