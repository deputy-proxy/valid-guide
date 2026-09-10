<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Models\EvaluationRequest;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationRequestStateTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function evaluationRequestForTransition(?User $member = null): EvaluationRequest
{
    $organization = DB::table('organizations')->insertGetId([
        'name' => 'Transition Test',
        'slug' => 'transition-test-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $member ??= User::factory()->create();
    DB::table('organization_user')->insert([
        'organization_id' => $organization,
        'user_id' => $member->getKey(),
        'role' => OrganizationRole::Editor->value,
    ]);

    $product = Product::create([
        'organization_id' => $organization,
        'title' => 'Course',
        'slug' => 'transition-course-'.uniqid(),
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'release-1',
        'title_snapshot' => 'Course',
        'version' => '1.0',
        'status' => 'draft',
    ]);

    $package = ServicePackage::create([
        'name' => 'Standard Evaluation',
        'slug' => 'transition-evaluation-'.uniqid(),
        'description' => 'Evaluation package.',
        'product_types' => ['course'],
        'complexity_levels' => ['standard'],
        'price' => 100,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    return EvaluationRequest::create([
        'organization_id' => $organization,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'service_package_id' => $package->id,
        'service_package' => $package->slug,
        'service_package_name_snapshot' => $package->name,
        'service_package_description_snapshot' => $package->description,
        'service_package_terms_snapshot' => [
            'name' => $package->name,
            'description' => $package->description,
        ],
        'complexity' => 'standard',
        'quoted_price' => 100,
        'currency' => 'EUR',
        'status' => EvaluationRequestStatus::Draft,
    ]);
}

it('allows only explicitly defined evaluation request transitions', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(EvaluationRequestStatus::AwaitingPayment)
        ->and($fresh->submitted_at)->not->toBeNull()
        ->and($fresh->payment_started_at)->not->toBeNull();
});

it('rejects invalid evaluation request transitions', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);

    expect(fn () => app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::Paid, $actor))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects submission when the exact product release is missing', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);
    $request->product_release_id = null;
    $request->saveQuietly();

    expect(fn () => app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects a product from a different organization', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);

    $otherOrganization = DB::table('organizations')->insertGetId([
        'name' => 'Other Organization',
        'slug' => 'other-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherProduct = Product::create([
        'organization_id' => $otherOrganization,
        'title' => 'Other Course',
        'slug' => 'other-course-'.uniqid(),
    ]);

    expect(fn () => $request->update(['product_id' => $otherProduct->id]))
        ->toThrow(DomainStateTransitionException::class);
});

it('prevents direct lifecycle timestamp and status mutation', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);

    expect(fn () => $request->update(['status' => EvaluationRequestStatus::AwaitingPayment]))
        ->toThrow(DomainStateTransitionException::class)
        ->and(fn () => $request->update(['submitted_at' => now()]))
        ->toThrow(DomainStateTransitionException::class);
});

it('freezes commercial terms after payment processing starts', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);
    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);

    expect(fn () => $request->update(['quoted_price' => 200]))
        ->toThrow(DomainStateTransitionException::class)
        ->and(fn () => $request->update(['currency' => 'USD']))
        ->toThrow(DomainStateTransitionException::class)
        ->and(fn () => $request->update(['complexity' => 'complex']))
        ->toThrow(DomainStateTransitionException::class)
        ->and(fn () => $request->update(['service_package_name_snapshot' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});

it('records the actor on successful lifecycle audit entries', function () {
    $actor = User::factory()->create();
    $request = evaluationRequestForTransition($actor);

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);

    expect(DB::table('audit_logs')
        ->where('auditable_type', EvaluationRequest::class)
        ->where('auditable_id', $request->id)
        ->where('event', 'evaluation_request.status_changed')
        ->whereJsonContains('after', ['actor_id' => $actor->getKey()])
        ->exists())->toBeTrue();
});

it('does not let an outsider submit or view an evaluation request', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $request = evaluationRequestForTransition($member);

    expect(Gate::forUser($outsider)->allows('view', $request))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('submit', $request))->toBeFalse()
        ->and(Gate::forUser($outsider)->allows('cancel', $request))->toBeFalse();
});
