<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('rate limits public verification lookup and display routes', function (): void {
    $lookup = Route::getRoutes()->getByName('public.verify');
    $display = Route::getRoutes()->getByName('public.verify.show');

    expect($lookup)->not->toBeNull();
    expect($display)->not->toBeNull();
    expect($lookup?->gatherMiddleware())->toContain('throttle:30,1');
    expect($display?->gatherMiddleware())->toContain('throttle:30,1');
});

it('rate limits authenticated community reporting', function (): void {
    $route = Route::getRoutes()->getByName('community.reports.store');

    expect($route)->not->toBeNull();
    expect($route?->gatherMiddleware())->toContain('auth');
    expect($route?->gatherMiddleware())->toContain('verified');
    expect($route?->gatherMiddleware())->toContain('throttle:10,1');
});
