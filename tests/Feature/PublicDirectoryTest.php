<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\PublicDirectoryEntry;
use App\Models\User;
use App\Services\ProductSuitability;
use App\Services\ValidationIssuance;
use App\Services\ValidationStateTransition;

function validatedDirectoryFixture(): array
{
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();
    $product = $evaluation->productRelease->product;

    app(ProductSuitability::class)->update($creator, $product, [
        'matching_audiences' => [ProductAudience::Professionals->value],
        'matching_goals' => [ProductGoal::ProfessionalDevelopment->value],
    ]);

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    $validation = app(ValidationIssuance::class)->issue($evaluation, $admin);

    return [$validation, $product, $creator, $admin];
}

it('publishes validated products to the public directory and links to authoritative verification', function () {
    [$validation] = validatedDirectoryFixture();

    $entry = PublicDirectoryEntry::query()->where('verification_identifier', $validation->verification_identifier)->first();

    expect($entry)->not->toBeNull()
        ->and($entry?->validation_status)->toBe(ValidationStatus::Active)
        ->and($entry?->directory_visible)->toBeTrue();

    $response = $this->get(route('public.directory'));

    $response->assertOk()
        ->assertSee('Find validated learning products')
        ->assertSee($validation->verification_identifier)
        ->assertSee(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]), false);
});

it('searches and filters directory results using deterministic public metadata', function () {
    [$validation, $product] = validatedDirectoryFixture();

    $response = $this->get(route('public.directory', [
        'q' => $product->title,
        'audience' => ProductAudience::Professionals->value,
        'goal' => ProductGoal::ProfessionalDevelopment->value,
        'product_type' => ProductType::Course->value,
        'subject_area' => $product->subject_area,
        'language' => $product->language,
    ]));

    $response->assertOk()
        ->assertSee($product->title)
        ->assertSee($validation->verification_identifier);

    $this->get(route('public.directory', ['audience' => ProductAudience::Beginners->value]))
        ->assertOk()
        ->assertDontSee($product->title);
});

it('does not present suspended validation as currently validated in the directory', function () {
    [$validation, $product, , $admin] = validatedDirectoryFixture();

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Suspended,
        $admin,
        'Temporary validation suspension for directory regression test.',
    );

    expect(PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->value('validation_status'))->toBe(ValidationStatus::Suspended->value);

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($product->title);
});

it('removes a hidden directory record from public discovery without deleting verification history', function () {
    [$validation, $product, , $admin] = validatedDirectoryFixture();
    $record = $validation->publicVerificationRecord()->firstOrFail();

    app(\App\Services\PublicVerificationPublication::class)->setVisibility($record, false, false, $admin);

    expect($validation->publicVerificationRecord()->first())->not->toBeNull()
        ->and(PublicDirectoryEntry::query()
            ->where('verification_identifier', $validation->verification_identifier)
            ->value('directory_visible'))->toBeFalse();

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($product->title);
});

it('does not expose internal creator data through the directory projection', function () {
    [$validation, $product, $creator] = validatedDirectoryFixture();

    $response = $this->get(route('public.directory'));

    $response->assertOk()
        ->assertSee($product->title)
        ->assertDontSee($creator->email)
        ->assertDontSee('payment')
        ->assertDontSee('auditor');
});
