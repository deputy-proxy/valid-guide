<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\ValidationStatus;
use App\Models\PublicDirectoryEntry;
use App\Models\User;
use App\Services\PublicVerificationPublication;
use App\Services\ValidationStateTransition;

it('renders a public product detail page from public projection data', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $entry = PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->firstOrFail();

    $response = $this->get(route('public.products.show', ['slug' => $entry->slug]));

    $response->assertOk()
        ->assertSee('Test Product 1.0')
        ->assertSee('Test Creator')
        ->assertSee($validation->verification_identifier)
        ->assertSee(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]), false)
        ->assertSee('Current product page vs. verification history');
});

it('keeps product detail isolated from mutable internal product data', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $entry = PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->firstOrFail();

    $validation->productRelease->product->organization->update(['name' => 'Private Internal Creator Name']);
    $validation->productRelease->product->update(['title' => 'Private Internal Product Name']);

    $this->get(route('public.products.show', ['slug' => $entry->slug]))
        ->assertOk()
        ->assertSee('Test Creator')
        ->assertSee('Test Product 1.0')
        ->assertDontSee('Private Internal Creator Name')
        ->assertDontSee('Private Internal Product Name');
});

it('does not expose private creator, auditor, or payment data on the product page', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $entry = PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->firstOrFail();

    $validation->load('evaluation.auditorEvaluations.assignment.auditor');
    $validation->evaluation->auditorEvaluations->first()->assignment->auditor->update([
        'email' => 'private-auditor@example.test',
    ]);

    $this->get(route('public.products.show', ['slug' => $entry->slug]))
        ->assertOk()
        ->assertDontSee('private-auditor@example.test')
        ->assertDontSee('payment');
});

it('makes unavailable products non-disclosing after their validation leaves the public directory', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);
    $entry = PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->firstOrFail();
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Revoked,
        $admin,
        'Revoked for product detail regression test.',
    );

    $this->get(route('public.products.show', ['slug' => $entry->slug]))
        ->assertOk()
        ->assertSee('This product is not currently available')
        ->assertDontSee('Test Creator')
        ->assertDontSee($validation->verification_identifier);

    $this->get(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]))
        ->assertOk()
        ->assertSee('This verification has been revoked');
});

it('does not expose unpublished product projections', function (): void {
    $response = $this->get(route('public.products.show', ['slug' => 'not-published']));

    $response->assertOk()
        ->assertSee('This product is not currently available')
        ->assertSee('Historical verification records remain authoritative');
});
