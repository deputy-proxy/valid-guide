<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\ExpertiseArea;
use App\Enums\MarketplaceServiceStatus;
use App\Enums\MarketplaceTransactionStatus;
use App\Enums\PlatformRole;
use App\Models\AuditorProfile;
use App\Models\ExpertBoardMembership;
use App\Models\ExpertPublicProfile;
use App\Models\MarketplaceService;
use App\Models\MarketplaceTransaction;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\MarketplaceServiceManagement;
use App\Services\MarketplaceTransactionService;
use Illuminate\Support\Facades\DB;

function marketplaceExpert(bool $eligible = true): User
{
    $user = User::factory()->create();
    $profile = AuditorProfile::create([
        'auditor_id' => $user->id,
        'status' => $eligible ? AuditorProfileStatus::Approved : AuditorProfileStatus::Pending,
    ]);

    ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => $eligible ? ExpertBoardMembershipStatus::Approved : ExpertBoardMembershipStatus::Pending,
        'approved_at' => $eligible ? now() : null,
    ]);

    ExpertPublicProfile::create([
        'auditor_profile_id' => $profile->id,
        'slug' => 'expert-'.uniqid(),
        'display_name' => $user->name,
        'status' => $eligible ? ExpertPublicProfileStatus::Published : ExpertPublicProfileStatus::Draft,
        'published_at' => $eligible ? now() : null,
    ]);

    return $user;
}

function marketplaceService(User $expert, bool $published = false, string $title = 'Expert service'): MarketplaceService
{
    $profile = $expert->auditorProfile;
    $service = MarketplaceService::create([
        'auditor_profile_id' => $profile->id,
        'title' => $title,
        'slug' => 'service-'.uniqid(),
        'description' => 'A governed Expert service.',
        'expertise_areas' => [ExpertiseArea::Assessment->value],
        'product_types' => ['course'],
        'price_minor' => 10000,
        'currency' => 'EUR',
        'status' => MarketplaceServiceStatus::Draft,
    ]);

    if ($published) {
        DB::table('marketplace_services')->whereKey($service->id)->update([
            'status' => MarketplaceServiceStatus::Published->value,
            'published_at' => now(),
        ]);
        $service->refresh();
    }

    return $service;
}

it('allows only eligible Experts to create marketplace services', function () {
    $eligible = marketplaceExpert();
    $ineligible = marketplaceExpert(false);
    $management = app(MarketplaceServiceManagement::class);
    $attributes = [
        'title' => 'Assessment review',
        'slug' => 'assessment-review-'.uniqid(),
        'description' => 'Independent review service.',
        'expertise_areas' => [ExpertiseArea::Assessment->value],
        'product_types' => ['course'],
        'price_minor' => 10000,
        'currency' => 'EUR',
    ];

    expect($management->create($eligible, $attributes)->status)->toBe(MarketplaceServiceStatus::Draft)
        ->and(fn () => $management->create($ineligible, $attributes))->toThrow(DomainStateTransitionException::class);
});

it('publishes only eligible owned draft services and protects published details', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert);
    $management = app(MarketplaceServiceManagement::class);

    $published = $management->publish($expert, $service);

    expect($published->status)->toBe(MarketplaceServiceStatus::Published)
        ->and($published->published_at)->not->toBeNull();

    expect(fn () => $published->update(['title' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});

it('discovers only published eligible services without commercial ranking signals', function () {
    $first = marketplaceExpert();
    $second = marketplaceExpert();
    $firstService = marketplaceService($first, true, 'A service');
    $secondService = marketplaceService($second, true, 'B service');

    $results = app(\App\Services\MarketplaceDiscovery::class)->search();

    expect($results->pluck('id')->all())->toBe([$firstService->id, $secondService->id]);
});

it('creates transactions with a frozen commercial snapshot', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert, true);
    $buyer = User::factory()->create();

    $transaction = app(MarketplaceTransactionService::class)->create($buyer, $service);

    expect($transaction->status)->toBe(MarketplaceTransactionStatus::Pending)
        ->and($transaction->amount_minor)->toBe(10000)
        ->and($transaction->currency)->toBe('EUR');
});

it('enforces the marketplace transaction lifecycle', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert, true);
    $buyer = User::factory()->create();
    $workflow = app(MarketplaceTransactionService::class);
    $transaction = $workflow->create($buyer, $service);

    $workflow->markPaid($transaction, $buyer);
    $workflow->start($transaction->fresh(), $expert);
    $completed = $workflow->complete($transaction->fresh(), $expert);

    expect($completed->status)->toBe(MarketplaceTransactionStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull();
});

it('keeps completed transaction history immutable', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert, true);
    $buyer = User::factory()->create();
    $workflow = app(MarketplaceTransactionService::class);
    $transaction = $workflow->create($buyer, $service);
    $workflow->markPaid($transaction, $buyer);
    $workflow->start($transaction->fresh(), $expert);
    $completed = $workflow->complete($transaction->fresh(), $expert);

    expect(fn () => $completed->update(['amount_minor' => 1]))
        ->toThrow(DomainStateTransitionException::class);
});

it('enforces buyer and provider transaction authorization', function () {
    $expert = marketplaceExpert();
    $otherExpert = marketplaceExpert();
    $service = marketplaceService($expert, true);
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $transaction = app(MarketplaceTransactionService::class)->create($buyer, $service);

    expect(fn () => app(MarketplaceTransactionService::class)->markPaid($transaction, $otherBuyer))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class)
        ->and(fn () => app(MarketplaceTransactionService::class)->markPaid($transaction, $otherExpert))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('does not couple marketplace transactions to validation state', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert, true);
    $buyer = User::factory()->create();
    $validationBefore = DB::table('validations')->count();

    $transaction = app(MarketplaceTransactionService::class)->create($buyer, $service);
    app(MarketplaceTransactionService::class)->markPaid($transaction, $buyer);
    app(MarketplaceTransactionService::class)->cancel($transaction->fresh(), $buyer, 'Buyer cancelled');

    expect(DB::table('validations')->count())->toBe($validationBefore)
        ->and(MarketplaceTransaction::find($transaction->id)->status)->toBe(MarketplaceTransactionStatus::Cancelled);
});

it('allows platform administrators to refund marketplace transactions without changing validation records', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert, true);
    $buyer = User::factory()->create();
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    $workflow = app(MarketplaceTransactionService::class);
    $transaction = $workflow->create($buyer, $service);
    $workflow->markPaid($transaction, $buyer);
    $validationBefore = DB::table('validations')->count();

    $refunded = $workflow->refund($transaction->fresh(), $admin, 'Platform refund');

    expect($refunded->status)->toBe(MarketplaceTransactionStatus::Refunded)
        ->and(DB::table('validations')->count())->toBe($validationBefore);
});
