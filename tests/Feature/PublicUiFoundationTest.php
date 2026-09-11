<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('renders the public home page without authentication', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Valid.guide');
    $response->assertSee('Independent validation for courses, guides, and other online learning products.');
    $response->assertSee('<main id="main-content"', false);
    $response->assertSee('<nav aria-label="Primary navigation">', false);
    $response->assertSee('<nav aria-label="Footer navigation">', false);
    $response->assertSee('<meta name="description"', false);
    $response->assertSee('<link rel="canonical"', false);
    $response->assertSee('<meta property="og:title"', false);
});

it('provides the public information architecture as named routes', function () {
    expect(Route::has('public.how-it-works'))->toBeTrue()
        ->and(Route::has('public.creators'))->toBeTrue()
        ->and(Route::has('public.buyers'))->toBeTrue()
        ->and(Route::has('public.verify'))->toBeTrue()
        ->and(Route::has('public.pricing'))->toBeTrue()
        ->and(Route::has('public.faq'))->toBeTrue()
        ->and(Route::has('public.about'))->toBeTrue();
});

it('renders public foundation pages without authentication', function () {
    foreach ([
        'public.how-it-works',
        'public.creators',
        'public.buyers',
        'public.verify',
        'public.pricing',
        'public.faq',
        'public.about',
    ] as $routeName) {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertSee('<main id="main-content"', false);
        $response->assertSee('Skip to content');
        $response->assertSee('<nav aria-label="Primary navigation">', false);
    }
});

it('uses noindex for the verification foundation until lookup is implemented', function () {
    $response = $this->get(route('public.verify'));

    $response->assertOk();
    $response->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('does not put public routes behind authentication middleware', function () {
    foreach ([
        'home',
        'public.how-it-works',
        'public.creators',
        'public.buyers',
        'public.verify',
        'public.pricing',
        'public.faq',
        'public.about',
    ] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull();
        expect($route?->gatherMiddleware())->not->toContain('auth');
        expect($route?->gatherMiddleware())->not->toContain('verified');
    }
});
