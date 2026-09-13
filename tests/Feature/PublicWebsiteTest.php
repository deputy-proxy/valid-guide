<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('serves the public information pages without authentication', function (string $routeName, string $heading): void {
    $response = $this->get(route($routeName));

    $response->assertOk()
        ->assertSee($heading)
        ->assertDontSee('is being prepared for publication')
        ->assertDontSee('is being prepared.');
})->with([
    ['public.how-it-works', 'How validation works'],
    ['public.creators', 'Turn a quality claim into a verifiable trust signal'],
    ['public.buyers', 'Know what was evaluated before you trust the claim'],
    ['public.pricing', 'Pay for the evaluation, never for the result'],
    ['public.faq', 'Frequently asked questions'],
    ['public.about', 'A clearer trust signal for online learning'],
]);

it('publishes safe page-specific metadata for public information pages', function (string $routeName, string $title, string $description): void {
    $response = $this->get(route($routeName));

    $response->assertOk()
        ->assertSee('<title>'.$title.' · '.config('app.name', 'Valid.guide').'</title>', false)
        ->assertSee('<meta name="description" content="'.$description.'">', false)
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertSee('<link rel="canonical" href="'.route($routeName).'">', false);
})->with([
    ['public.how-it-works', 'How validation works', 'Understand how Valid.guide evaluates online learning products and publishes transparent trust records.'],
    ['public.creators', 'For creators', 'Learn how creators can submit an online learning product to Valid.guide for independent validation.'],
    ['public.buyers', 'For buyers and learners', 'Learn how to use Valid.guide records to make better-informed decisions about online learning products.'],
    ['public.pricing', 'Pricing', 'Understand how Valid.guide evaluation pricing works and why payment does not determine the validation outcome.'],
    ['public.faq', 'Frequently asked questions', 'Answers to common questions about Valid.guide validation, independence, public records and learner outcomes.'],
    ['public.about', 'About Valid.guide', 'Learn what Valid.guide is, why it exists and how its independent validation model works.'],
]);

it('connects public website pages to authoritative discovery and verification surfaces', function (): void {
    $response = $this->get(route('public.how-it-works'));

    $response->assertOk()
        ->assertSee(route('public.directory'), false)
        ->assertSee(route('public.verify'), false);

    $this->get(route('public.buyers'))
        ->assertOk()
        ->assertSee(route('public.directory'), false)
        ->assertSee(route('public.verify'), false);

    $this->get(route('public.faq'))
        ->assertOk()
        ->assertSee(route('public.verify'), false);
});

it('keeps creator entry behind the existing authenticated journey', function (): void {
    $response = $this->get(route('public.creators'));

    $response->assertOk()
        ->assertSee(route('dashboard'), false);

    $route = Route::getRoutes()->getByName('dashboard');

    expect($route)->not->toBeNull();
    expect($route?->gatherMiddleware())->toContain('auth');
    expect($route?->gatherMiddleware())->toContain('verified');
});

it('does not expose private or commercial implementation details in public website pages', function (): void {
    foreach ([
        'public.how-it-works',
        'public.creators',
        'public.buyers',
        'public.pricing',
        'public.faq',
        'public.about',
    ] as $routeName) {
        $response = $this->get(route($routeName));

        $response->assertOk()
            ->assertDontSee('auditor@example')
            ->assertDontSee('evaluation_id')
            ->assertDontSee('payment_id')
            ->assertDontSee('internal');
    }
});
