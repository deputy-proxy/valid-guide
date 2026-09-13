<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\PublicDirectoryEntry;
use App\Models\User;
use App\Services\ProductRecommendations;
use App\Services\ProductSuitability;
use App\Services\PublicVerificationPublication;
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
        ->assertDontSee($validation->verification_identifier);
});

it('renders an explicit state and no results for invalid enum filters', function () {
    [$validation] = validatedDirectoryFixture();

    $this->get(route('public.directory', ['audience' => 'not-a-supported-audience']))
        ->assertOk()
        ->assertSee('Invalid directory filter')
        ->assertSee('Choose a supported filter value')
        ->assertDontSee($validation->verification_identifier);
});

it('does not recommend an unrelated product for a search query', function () {
    [$validation] = validatedDirectoryFixture();

    $recommendations = app(ProductRecommendations::class)->recommend(query: 'query-that-cannot-match-anything');

    expect($recommendations)->toBeEmpty();

    $this->get(route('public.directory', ['q' => 'query-that-cannot-match-anything']))
        ->assertOk()
        ->assertDontSee($validation->verification_identifier)
        ->assertSee('No matching products');
});

it('limits recommendations to the requested result count', function () {
    validatedDirectoryFixture();
    validatedDirectoryFixture();
    validatedDirectoryFixture();
    validatedDirectoryFixture();

    $recommendations = app(ProductRecommendations::class)->recommend(limit: 2);

    expect($recommendations)->toHaveCount(2);
});

it('orders equally scored directory entries deterministically', function () {
    [$firstValidation] = validatedDirectoryFixture();
    [$secondValidation] = validatedDirectoryFixture();

    PublicDirectoryEntry::query()
        ->where('verification_identifier', $firstValidation->verification_identifier)
        ->update(['title' => 'Same Directory Title']);
    PublicDirectoryEntry::query()
        ->where('verification_identifier', $secondValidation->verification_identifier)
        ->update(['title' => 'Same Directory Title']);

    $expectedOrder = [$firstValidation->verification_identifier, $secondValidation->verification_identifier];
    sort($expectedOrder);

    $response = $this->get(route('public.directory'));

    $response->assertOk()->assertSeeInOrder($expectedOrder);
});

it('paginates directory results while preserving filters', function () {
    $validationIdentifiers = [];

    for ($index = 0; $index < 13; $index++) {
        [$validation] = validatedDirectoryFixture();
        $validationIdentifiers[] = $validation->verification_identifier;
    }

    $response = $this->get(route('public.directory', [
        'audience' => ProductAudience::Professionals->value,
        'page' => 2,
    ]));

    $response->assertOk()
        ->assertSee(end($validationIdentifiers))
        ->assertSee('audience='.ProductAudience::Professionals->value, false);
});

it('does not present suspended validation as currently validated in the directory', function () {
    [$validation, , , $admin] = validatedDirectoryFixture();

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Suspended,
        $admin,
        'Temporary validation suspension for directory regression test.',
    );

    expect(PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->value('validation_status'))->toBe(ValidationStatus::Suspended);

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($validation->verification_identifier);
});

it('removes a hidden directory record from public discovery without deleting verification history', function () {
    [$validation, , , $admin] = validatedDirectoryFixture();
    $record = $validation->publicVerificationRecord()->firstOrFail();

    app(PublicVerificationPublication::class)->setVisibility($record, false, false, $admin);

    expect($validation->publicVerificationRecord()->first())->not->toBeNull()
        ->and(PublicDirectoryEntry::query()
            ->where('verification_identifier', $validation->verification_identifier)
            ->value('directory_visible'))->toBeFalse();

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($validation->verification_identifier);
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
