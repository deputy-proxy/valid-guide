<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentStatus;
use App\Models\EvaluationRequest;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function filamentCreatorDashboardFixture(string $role): array
{
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Filament Dashboard Organization',
        'slug' => 'filament-dashboard-organization-'.$user->id,
        'status' => 'active',
    ]);
    $organization->users()->attach($user, ['role' => $role]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Dashboard Product',
        'slug' => 'dashboard-product-'.$user->id,
        'product_type' => 'course',
        'description' => 'A product for dashboard testing.',
        'canonical_url' => 'https://example.test/dashboard-product',
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

    $request = EvaluationRequest::create([
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
        'intake_notes' => json_encode([
            'claims_confirmed' => true,
            'audience_confirmed' => true,
            'scope' => 'Evaluate the complete product.',
            'material' => [
                'type' => 'url',
                'label' => 'Course URL',
                'location' => 'https://example.test/dashboard-product',
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    return [$user, $organization, $product, $release, $package, $request];
}

it('renders the creator dashboard with product, release, intake and next action', function () {
    [$user, $organization, $product, $release, , $request] = filamentCreatorDashboardFixture(OrganizationRole::Editor->value);
    session(['creator.organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get('/admin/creator-dashboard')
        ->assertSuccessful()
        ->assertSee('Creator Dashboard')
        ->assertSee($product->title)
        ->assertSee($release->release_identifier)
        ->assertSee('Resume Request')
        ->assertSee('Intake readiness')
        ->assertSee('Claims: Complete')
        ->assertSee('Audience: Complete')
        ->assertSee((string) $request->id);
});

it('renders commerce-only data for billing users', function () {
    [$user, $organization, $product, $release, $package, $request] = filamentCreatorDashboardFixture(OrganizationRole::Billing->value);
    session(['creator.organization_id' => $organization->id]);

    $order = Order::create([
        'organization_id' => $organization->id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => 'pending',
        'provider' => 'stripe',
    ]);
    Payment::create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_filament_dashboard',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get('/admin/creator-dashboard')
        ->assertSuccessful()
        ->assertSee($package->name)
        ->assertSee('EUR')
        ->assertSee('Pending')
        ->assertDontSee($product->title)
        ->assertDontSee($release->release_identifier)
        ->assertDontSee('Intake readiness');
});

it('does not render another organization data', function () {
    [$user, $organization, $product] = filamentCreatorDashboardFixture(OrganizationRole::Editor->value);
    [, $otherOrganization, $otherProduct] = filamentCreatorDashboardFixture(OrganizationRole::Editor->value);
    session(['creator.organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get('/admin/creator-dashboard')
        ->assertSuccessful()
        ->assertSee($product->title)
        ->assertDontSee($otherProduct->title)
        ->assertDontSee($otherOrganization->name);
});

it('shows a start action when there are no evaluation requests', function () {
    [$user, $organization] = filamentCreatorDashboardFixture(OrganizationRole::Editor->value);
    EvaluationRequest::query()->delete();
    session(['creator.organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get('/admin/creator-dashboard')
        ->assertSuccessful()
        ->assertSee('No evaluation requests are available for this organization.')
        ->assertSee('Start an evaluation request');
});

it('hides the dashboard from users without an organization', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/creator-dashboard')
        ->assertForbidden();
});

it('renders the refund action only when the backend contract says it is eligible', function () {
    [$user, $organization, , , , $request] = filamentCreatorDashboardFixture(OrganizationRole::Editor->value);
    $request->update(['status' => EvaluationRequestStatus::Paid->value]);
    $order = Order::create([
        'organization_id' => $organization->id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => 'paid',
        'provider' => 'stripe',
    ]);
    Payment::create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_refund_dashboard',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);
    session(['creator.organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get('/admin/creator-dashboard')
        ->assertSuccessful()
        ->assertSee('Request refund');
});
