<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Models\Product;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationRequestStateTransition;
use Illuminate\Support\Facades\DB;

function evaluationRequestForTransition(): EvaluationRequest
{
    $organization = DB::table('organizations')->insertGetId([
        'name' => 'Transition Test',
        'slug' => 'transition-test-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::create([
        'organization_id' => $organization,
        'title' => 'Course',
        'slug' => 'transition-course-'.uniqid(),
    ]);

    return EvaluationRequest::create([
        'organization_id' => $organization,
        'product_id' => $product->id,
        'service_package' => 'standard',
        'complexity' => 'standard',
        'quoted_price' => 100,
        'currency' => 'EUR',
        'status' => EvaluationRequestStatus::Draft,
    ]);
}

it('allows only explicitly defined evaluation request transitions', function () {
    $request = evaluationRequestForTransition();

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment);

    expect($request->fresh()->status)->toBe(EvaluationRequestStatus::AwaitingPayment);
});

it('rejects invalid evaluation request transitions', function () {
    $request = evaluationRequestForTransition();

    expect(fn () => app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::Paid))
        ->toThrow(DomainStateTransitionException::class);
});
