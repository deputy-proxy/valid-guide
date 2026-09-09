<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\ProductType;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\ServicePackage;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationRequestStateTransition;
use App\Services\ReportDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function reportDeliveryFixture(): array
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

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => '2026-01',
        'title_snapshot' => $product->title,
        'version' => '1.0',
    ]);

    $package = ServicePackage::create([
        'name' => 'Standard Validation',
        'slug' => 'standard-validation',
        'description' => 'Standard validation package',
        'price' => 500,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    $request = EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'service_package_id' => $package->id,
        'service_package' => $package->slug,
        'service_package_name_snapshot' => $package->name,
        'service_package_description_snapshot' => $package->description,
        'complexity' => 'standard',
        'quoted_price' => $package->price,
        'currency' => $package->currency,
    ]);

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment);
    app(EvaluationRequestStateTransition::class)->transition($request->fresh(), EvaluationRequestStatus::Paid);

    $standard = EvaluationStandard::create([
        'name' => 'Report Standard',
        'slug' => 'report-standard',
    ]);
    $standardVersion = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'effective',
    ]);
    Criterion::create([
        'standard_version_id' => $standardVersion->id,
        'code' => 'REPORT-01',
        'name' => 'Report criterion',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 100,
    ]);

    $evaluation = Evaluation::create([
        'evaluation_request_id' => $request->id,
        'product_release_id' => $release->id,
        'standard_version_id' => $standardVersion->id,
    ]);

    $report = Report::create(['evaluation_id' => $evaluation->id]);

    $version = ReportVersion::create([
        'report_id' => $report->id,
        'version_number' => 1,
        'content_structure' => ['decision' => 'not validated'],
        'decision_snapshot' => ['decision' => 'not validated'],
        'standard_version_snapshot' => [],
        'created_by' => User::factory()->create()->id,
    ]);

    DB::table('reports')->where('id', $report->id)->update([
        'current_version_id' => $version->id,
        'updated_at' => now(),
    ]);

    return [$request->fresh(), $report->fresh()];
}

test('report delivery is controlled and immutable', function () {
    [, $report] = reportDeliveryFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $delivered = app(ReportDelivery::class)->deliver($report, $admin);

    expect($delivered->delivered_at)->not->toBeNull();

    $delivered->delivered_at = null;

    expect(fn () => $delivered->save())->toThrow(DomainStateTransitionException::class);
});

test('report delivery cannot be recorded twice', function () {
    [, $report] = reportDeliveryFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    app(ReportDelivery::class)->deliver($report, $admin);

    expect(fn () => app(ReportDelivery::class)->deliver($report, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

test('non platform administrators cannot record report delivery', function () {
    [, $report] = reportDeliveryFixture();
    $user = User::factory()->create();

    expect(fn () => app(ReportDelivery::class)->deliver($report, $user))
        ->toThrow(DomainStateTransitionException::class);
});
