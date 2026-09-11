<?php

declare(strict_types=1);

use App\Livewire\Creator\EvaluationRequests\CreateEvaluationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\CreatorEvaluationRequestIntake;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Throwable;

it('keeps public verification and layout landmarks accessible', function (): void {
    $response = $this->get(route('public.verify'));

    $response->assertOk()
        ->assertSee('<header', false)
        ->assertSee('<nav aria-label="Primary navigation">', false)
        ->assertSee('<main id="main-content"', false)
        ->assertSee('<nav aria-label="Footer navigation">', false)
        ->assertSee('href="#main-content"', false)
        ->assertSee('focus:ring-2', false);

    $html = $response->getContent();

    expect(substr_count($html, '<main '))->toBe(1)
        ->and(substr_count($html, 'aria-label="Primary navigation"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="Footer navigation"'))->toBe(1);
});

it('does not allow Livewire clients to replace the creator tenant context', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $organization->users()->attach($user, ['role' => 'owner']);
    $this->actingAs($user);

    $component = Livewire::test(CreateEvaluationRequest::class, [
        'organizationId' => $organization->id,
    ]);

    expect(fn () => $component->set('organizationId', $otherOrganization->id))
        ->toThrow(fn (Throwable $exception): bool => $exception instanceof \Livewire\Exceptions\CannotUpdateLockedPropertyException);
});

it('does not allow Livewire clients to replace the resumed request identifier', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $organization->users()->attach($user, ['role' => 'owner']);
    $otherOrganization->users()->attach($user, ['role' => 'owner']);
    $this->actingAs($user);

    $intake = app(CreatorEvaluationRequestIntake::class);
    $request = $intake->start($user, $organization->id);
    $otherRequest = $intake->start($user, $otherOrganization->id);

    $component = Livewire::test(CreateEvaluationRequest::class, [
        'organizationId' => $organization->id,
        'evaluationRequestId' => $request->id,
    ]);

    expect(fn () => $component->set('evaluationRequestId', $otherRequest->id))
        ->toThrow(fn (Throwable $exception): bool => $exception instanceof \Livewire\Exceptions\CannotUpdateLockedPropertyException);
});

it('keeps the public verification route outside authentication middleware', function (): void {
    $route = Route::getRoutes()->getByName('public.verify');

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->not->toContain('auth')
        ->and($route?->gatherMiddleware())->not->toContain('verified');
});
