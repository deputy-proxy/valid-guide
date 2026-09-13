<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\MarketplaceServiceStatus;
use App\Enums\ExpertiseArea;
use App\Models\AuditorProfile;
use App\Models\ExpertBoardMembership;
use App\Models\ExpertPublicProfile;
use App\Models\MarketplaceService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function marketplaceUiExpert(): User
{
    $user = User::factory()->create();
    $profile = AuditorProfile::create([
        'auditor_id' => $user->id,
        'status' => AuditorProfileStatus::Approved,
    ]);

    ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Approved,
        'approved_at' => now(),
    ]);

    ExpertPublicProfile::create([
        'auditor_profile_id' => $profile->id,
        'slug' => 'ui-expert-'.uniqid(),
        'display_name' => $user->name,
        'status' => ExpertPublicProfileStatus::Published,
        'published_at' => now(),
    ]);

    return $user;
}

function marketplaceUiService(User $expert, bool $published, string $title): MarketplaceService
{
    $service = MarketplaceService::create([
        'auditor_profile_id' => $expert->auditorProfile->id,
        'title' => $title,
        'slug' => 'ui-service-'.uniqid(),
        'description' => 'A governed Expert service.',
        'expertise_areas' => [ExpertiseArea::Assessment->value],
        'product_types' => ['course'],
        'price_minor' => 10000,
        'currency' => 'EUR',
        'status' => $published ? MarketplaceServiceStatus::Published : MarketplaceServiceStatus::Draft,
        'published_at' => $published ? now() : null,
    ]);

    return $service;
}

it('renders the public marketplace and excludes unavailable services', function () {
    $expert = marketplaceUiExpert();
    $published = marketplaceUiService($expert, true, 'Published service');
    $paused = marketplaceUiService($expert, false, 'Paused service');
    DB::table('marketplace_services')->whereKey($paused->id)->update(['status' => MarketplaceServiceStatus::Paused->value]);

    $response = $this->get(route('public.marketplace'));

    $response->assertOk()
        ->assertSee($published->title)
        ->assertDontSee($paused->title);
});

it('requires authentication to request a marketplace service', function () {
    $expert = marketplaceUiExpert();
    $service = marketplaceUiService($expert, true, 'Expert service');

    $this->post(route('creator.marketplace.purchase', $service))
        ->assertRedirect(route('login'));
});

it('renders the authenticated expert and creator marketplace pages', function () {
    $expert = marketplaceUiExpert();
    $buyer = User::factory()->create();

    $this->actingAs($expert)
        ->get(route('expert.marketplace'))
        ->assertOk()
        ->assertSee('Your services');

    $this->actingAs($buyer)
        ->get(route('creator.marketplace'))
        ->assertOk()
        ->assertSee('Your marketplace engagements');
});
