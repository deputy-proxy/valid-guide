<?php

declare(strict_types=1);

use App\Enums\MarketplaceServiceStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('renders the public marketplace and excludes unavailable services', function () {
    $expert = marketplaceExpert();
    $published = marketplaceService($expert, true, 'Published service');
    $paused = marketplaceService($expert, false, 'Paused service');
    DB::table('marketplace_services')->whereKey($paused->id)->update(['status' => MarketplaceServiceStatus::Paused->value]);

    $response = $this->get(route('public.marketplace'));

    $response->assertOk()
        ->assertSee($published->title)
        ->assertDontSee($paused->title);
});

it('requires authentication to request a marketplace service', function () {
    $expert = marketplaceExpert();
    $service = marketplaceService($expert, true);

    $this->post(route('creator.marketplace.purchase', $service))
        ->assertRedirect(route('login'));
});

it('renders the authenticated expert and creator marketplace pages', function () {
    $expert = marketplaceExpert();
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
