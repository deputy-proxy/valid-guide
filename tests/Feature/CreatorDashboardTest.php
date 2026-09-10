<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CreatorDashboard;
use Illuminate\Support\Facades\DB;

function creatorDashboardFixture(string $role = 'owner'): array
{
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Dashboard Organization',
        'slug' => 'dashboard-organization-'.$user->id,
        'status' => 'active',
    ]);
    $organization->users()->attach($user, ['role' => $role]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Dashboard Course',
        'slug' => 'dashboard-course-'.$user->id,
        'product_type' => 'course',
        'description' => 'A course for dashboard testing.',
        'canonical_url' => 'https://example.test/dashboard-course',
        'target_audience' => 'Professional learners',
        'claimed_outcomes' => ['Learn the subject.'],
        'language' => 'en',
        'status' => 'active',
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'dashboard-release-'.$user->id,
        'title_snapshot' => $product->title,
        'version' => '1.0',
        'status' => 'draft',
    ]);
    DB::table('product_releases')->where('id', $release->id)->update(['status' => 'current']);
    $release->refresh();

    $package = ServicePackage::create([
        'name' => 'Dashboard Evaluation',
        'slug' => 'dashboard-evaluation-'.$user->id,
        'description' => 'Dashboard test package.',
        'product_types' => ['course'],
        'complexity_levels' => ['standard'],
        'price_minor' => 25000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    return [$user, $organization, $product, $release, $package];
}

function creatorDashboardRequest(
    Organization $organization,
    Product $product,
    ProductRelease $release,
    ServicePackage $package,
    EvaluationRequestStatus $status = EvaluationRequestStatus::Draft,
): EvaluationRequest {
    return EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'service_package_id' => $package->id,
        'service_package' => $package->name,
        'service_package_name_snapshot' => $package->name,
        'service_package_description_snapshot' => $package->description,
        'service_package_terms_snapshot' => ['price_minor' => $package->price_minor],
        'complexity' => 'standard',
        'quoted_price' => '250.00',
        'quoted_amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => $status,
        'intake_notes' => json_encode([
            'claims_confirmed' => true,
            'audience_confirmed' => true,
            'scope' => 'Evaluate the complete product.',
            'material' => [
                'type' => 'url',
                'label' => 'Course URL',
                'location' => 'https://example.test/dashboard-course',
            ],
        ], JSON_THROW_ON_ERROR),
    ]);
}

it('returns products, releases and request state for creator roles', function () {
    [$user, $organization, $product, $release, $package] = creatorDashboardFixture(OrganizationRole::Editor->value);
    $request = creatorDashboardRequest($organization, $product, $release, $package);

    $dashboard = app(CreatorDashboard::class)->forOrganization($user, $organization->id);

    expect($dashboard['organization']['role'])->toBe(OrganizationRole::Editor->value)
        ->and($dashboard['products'])->toHaveCount(1)
        ->and($dashboard['products'][0]['releases'][0]['id'])->toBe($release->id)
        ->and($dashboard['evaluation_requests'])->toHaveCount(1)
        ->and($dashboard['evaluation_requests'][0]['id'])->toBe($request->id)
        ->and($dashboard['evaluation_requests'][0]['stage'])->toBe('draft')
        ->and($dashboard['evaluation_requests'][0]['next_action'])->toBe('resume_request');
});

it('returns billing commerce data without creator product or intake data', function () {
    [$user, $organization, $product, $release, $package] = creatorDashboardFixture(OrganizationRole::Billing->value);
    $request = creatorDashboardRequest($organization, $product, $release, $package, EvaluationRequestStatus::AwaitingPayment);

    $order = Order::create([
        'organization_id' => $organization->id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Pending,
        'provider' => 'stripe',
    ]);
    Payment::create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_dashboard',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Pending,
    ]);

    $dashboard = app(CreatorDashboard::class)->forOrganization($user, $organization->id);
    $summary = $dashboard['evaluation_requests'][0];

    expect($summary)->not->toHaveKey('product')
        ->and($summary)->not->toHaveKey('product_release')
        ->and($summary)->not->toHaveKey('intake')
        ->and($summary['commerce']['package'])->toBe($package->name)
        ->and($summary['commerce']['amount_minor'])->toBe(25000)
        ->and($summary['commerce']['currency'])->toBe('EUR')
        ->and($summary['commerce']['payment_status'])->toBe(PaymentStatus::Pending->value)
        ->and($summary['next_action'])->toBe('view_payment');
});

it('rejects users outside the organization', function () {
    [, $organization] = creatorDashboardFixture();
    $otherUser = User::factory()->create();

    expect(fn () => app(CreatorDashboard::class)->forOrganization($otherUser, $organization->id))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('does not leak another organizations products or requests', function () {
    [$user, $organization, $product, $release, $package] = creatorDashboardFixture();
    creatorDashboardRequest($organization, $product, $release, $package);

    [, $otherOrganization, $otherProduct, $otherRelease, $otherPackage] = creatorDashboardFixture();
    creatorDashboardRequest($otherOrganization, $otherProduct, $otherRelease, $otherPackage);

    $dashboard = app(CreatorDashboard::class)->forOrganization($user, $organization->id);

    expect($dashboard['products'])->toHaveCount(1)
        ->and($dashboard['products'][0]['id'])->toBe($product->id)
        ->and($dashboard['evaluation_requests'])->toHaveCount(1)
        ->and($dashboard['evaluation_requests'][0]['product']['id'])->toBe($product->id)
        ->and($dashboard['evaluation_requests'][0]['product']['id'])->not->toBe($otherProduct->id);
});

it('maps the creator lifecycle to controlled next actions', function () {
    [$user, $organization, $product, $release, $package] = creatorDashboardFixture();

    $expected = [
        EvaluationRequestStatus::Draft->value => 'resume_request',
        EvaluationRequestStatus::AwaitingPayment->value => 'continue_payment',
        EvaluationRequestStatus::Paid->value => 'complete_intake',
        EvaluationRequestStatus::Intake->value => 'complete_intake',
        EvaluationRequestStatus::AwaitingCreator->value => 'complete_intake',
        EvaluationRequestStatus::Ready->value => 'view_status',
        EvaluationRequestStatus::Cancelled->value => null,
        EvaluationRequestStatus::Refunded->value => null,
    ];

    foreach ($expected as $status => $action) {
        EvaluationRequest::query()->delete();
        $request = creatorDashboardRequest(
            $organization,
            $product,
            $release,
            $package,
            EvaluationRequestStatus::from($status),
        );

        $summary = app(CreatorDashboard::class)->forOrganization($user, $organization->id)['evaluation_requests'][0];

        expect($summary['id'])->toBe($request->id)
            ->and($summary['next_action'])->toBe($action);
    }
});

it('exposes refund eligibility only when the backend refund preconditions are met', function () {
    [$user, $organization, $product, $release, $package] = creatorDashboardFixture();
    $request = creatorDashboardRequest($organization, $product, $release, $package, EvaluationRequestStatus::Paid);

    $order = Order::create([
        'organization_id' => $organization->id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Paid,
        'provider' => 'stripe',
    ]);
    Payment::create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_dashboard_refund',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $summary = app(CreatorDashboard::class)->forOrganization($user, $organization->id)['evaluation_requests'][0];

    expect($summary['commerce']['refund_eligible'])->toBeTrue()
        ->and($summary['next_action'])->toBe('request_refund');

    $order->update(['status' => OrderStatus::Refunded]);

    expect(app(CreatorDashboard::class)->forOrganization($user, $organization->id)['evaluation_requests'][0]['commerce']['refund_eligible'])
        ->toBeFalse();
});
