<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\EvaluationComplexity;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\MarketplaceServiceStatus;
use App\Enums\MarketplaceTransactionStatus;
use App\Filament\Resources\MarketplaceServices\MarketplaceServiceResource;
use App\Filament\Resources\MarketplaceTransactions\MarketplaceTransactionResource;
use App\Models\AuditLog;
use App\Models\AuditorProfile;
use App\Models\MarketplaceService;
use App\Models\MarketplaceTransaction;
use App\Models\User;
use App\Services\AuditorAssignmentCreation;
use App\Services\DomainStateTransitionException;
use App\Services\MarketplaceDiscovery;
use App\Services\MarketplaceServiceManagement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function governanceExpert(bool $eligible = true): User
{
    $user = User::factory()->create();
    $profile = AuditorProfile::query()->create([
        'auditor_id' => $user->id,
        'status' => $eligible ? AuditorProfileStatus::Approved : AuditorProfileStatus::Pending,
    ]);

    $profile->expertBoardMembership()->create([
        'status' => $eligible ? ExpertBoardMembershipStatus::Approved : ExpertBoardMembershipStatus::Pending,
        'approved_at' => $eligible ? now() : null,
    ]);

    $profile->expertPublicProfile()->create([
        'slug' => 'governance-expert-'.uniqid(),
        'display_name' => $user->name,
        'status' => $eligible ? ExpertPublicProfileStatus::Published : ExpertPublicProfileStatus::Draft,
        'published_at' => $eligible ? now() : null,
    ]);

    return $user;
}

function governanceService(User $expert, string $title = 'Governance service'): MarketplaceService
{
    return MarketplaceService::query()->create([
        'auditor_profile_id' => $expert->auditorProfile->id,
        'title' => $title,
        'slug' => 'governance-service-'.uniqid(),
        'description' => 'Governed marketplace service.',
        'expertise_areas' => ['assessment'],
        'product_types' => ['course'],
        'price_minor' => 10000,
        'currency' => 'EUR',
        'status' => MarketplaceServiceStatus::Draft,
    ]);
}

it('keeps marketplace lifecycle changes independent from validation state', function () {
    $expert = governanceExpert();
    $service = governanceService($expert);
    $management = app(MarketplaceServiceManagement::class);
    $validationCount = DB::table('validations')->count();

    $published = $management->publish($expert, $service);
    $paused = $management->pause($expert, $published);
    $management->archive($expert, $paused);

    expect(DB::table('validations')->count())->toBe($validationCount);
});

it('allows only platform administrators to govern marketplace services', function () {
    $expert = governanceExpert();
    $service = governanceService($expert);
    $management = app(MarketplaceServiceManagement::class);
    $published = $management->publish($expert, $service);
    $user = User::factory()->create();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    expect(Gate::forUser($user)->allows('pause', $published))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('pause', $published))->toBeTrue();
});

it('keeps marketplace discovery independent from transaction volume', function () {
    $first = governanceExpert();
    $second = governanceExpert();
    $firstService = governanceService($first, 'A service');
    $secondService = governanceService($second, 'B service');
    $management = app(MarketplaceServiceManagement::class);
    $firstService = $management->publish($first, $firstService);
    $secondService = $management->publish($second, $secondService);

    for ($index = 0; $index < 5; $index++) {
        MarketplaceTransaction::query()->create([
            'marketplace_service_id' => $firstService->id,
            'auditor_profile_id' => $firstService->auditor_profile_id,
            'buyer_id' => User::factory()->create()->id,
            'amount_minor' => $firstService->price_minor,
            'currency' => $firstService->currency,
            'status' => MarketplaceTransactionStatus::Pending,
        ]);
    }

    $results = app(MarketplaceDiscovery::class)->search();

    expect($results->pluck('id')->all())->toBe([$firstService->id, $secondService->id]);
});

it('blocks Auditor assignment after a marketplace relationship with the product organization', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $service = governanceService($auditor);
    $buyer = User::factory()->create();
    $organization = $evaluation->productRelease->product->organization;

    $transaction = MarketplaceTransaction::query()->create([
        'marketplace_service_id' => $service->id,
        'auditor_profile_id' => $service->auditor_profile_id,
        'buyer_id' => $buyer->id,
        'organization_id' => $organization->id,
        'amount_minor' => $service->price_minor,
        'currency' => $service->currency,
        'status' => MarketplaceTransactionStatus::Completed,
        'completed_at' => now(),
    ]);

    expect(fn () => app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        2,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    ))->toThrow(DomainStateTransitionException::class, 'marketplace commercial relationship');

    $audit = AuditLog::query()
        ->where('event', 'auditor_assignment.marketplace_conflict_detected')
        ->where('auditable_id', $evaluation->id)
        ->latest('created_at')
        ->first();

    expect(MarketplaceTransaction::query()->whereKey($transaction->id)->exists())->toBeTrue()
        ->and($audit)->not->toBeNull()
        ->and($audit?->after['conflict'])->toBe('marketplace_commercial_relationship')
        ->and($audit?->metadata['determined_by'])->toBe($admin->id);
});

it('allows assignment when the marketplace relationship belongs to another organization', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $service = governanceService($auditor);
    $buyer = User::factory()->create();
    $otherOrganization = DB::table('organizations')->insertGetId([
        'name' => 'Other organization',
        'slug' => 'other-organization-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    MarketplaceTransaction::query()->create([
        'marketplace_service_id' => $service->id,
        'auditor_profile_id' => $service->auditor_profile_id,
        'buyer_id' => $buyer->id,
        'organization_id' => $otherOrganization,
        'amount_minor' => $service->price_minor,
        'currency' => $service->currency,
        'status' => MarketplaceTransactionStatus::Completed,
        'completed_at' => now(),
    ]);

    $assignment = app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        2,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    );

    expect($assignment->auditor_id)->toBe($auditor->id);
});

it('restricts marketplace governance resources to platform administrators', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $this->actingAs($user);
    expect(MarketplaceServiceResource::canAccess())->toBeFalse()
        ->and(MarketplaceTransactionResource::canAccess())->toBeFalse();

    $this->actingAs($admin);
    expect(MarketplaceServiceResource::canAccess())->toBeTrue()
        ->and(MarketplaceTransactionResource::canAccess())->toBeTrue();
});

it('exposes the commercial independence disclosure on the public marketplace', function () {
    $expert = governanceExpert();
    $service = app(MarketplaceServiceManagement::class)->publish($expert, governanceService($expert, 'Independent service'));

    $this->get(route('public.marketplace.show', $service->slug))
        ->assertOk()
        ->assertSee('Independent marketplace')
        ->assertSee('does not influence Validation status, scoring, Auditor assignment, recommendations, or public trust records');
});
