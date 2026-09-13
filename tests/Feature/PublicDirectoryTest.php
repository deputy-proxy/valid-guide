<?php

declare(strict_types=1);

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

function validatedDirectoryFixture(): array
{
    $creator = User::factory()->create();
    $organization = \App\Models\Organization::factory()->create();
    \App\Models\OrganizationMembership::factory()->for($organization)->for($creator)->create([
        'role' => 'owner',
    ]);

    $product = \App\Models\Product::factory()->for($organization)->create([
        'name' => 'Directory Product '.fake()->unique()->numberBetween(1, 999999),
    ]);
    $release = \App\Models\ProductRelease::factory()->for($product)->create();
    $standardVersion = \App\Models\StandardVersion::factory()->create([
        'status' => 'approved',
        'effective_at' => now()->subDay(),
    ]);
    $evaluationRequest = \App\Models\EvaluationRequest::factory()
        ->for($organization)
        ->for($product)
        ->for($release, 'productRelease')
        ->create([
            'standard_version_id' => $standardVersion->id,
            'status' => 'completed',
        ]);
    $evaluation = \App\Models\Evaluation::factory()
        ->for($evaluationRequest)
        ->create([
            'status' => 'completed',
        ]);
    $validation = app(ValidationIssuance::class)->issue($evaluation, $creator);

    $publication = app(PublicVerificationPublication::class)->publish($validation, $creator);

    expect($publication->verification_identifier)->toBe($validation->verification_identifier);

    $entry = PublicDirectoryEntry::query()->where('verification_identifier', $validation->verification_identifier)->firstOrFail();

    app(ProductSuitability::class)->setMatchingMetadata(
        product: $product,
        actor: $creator,
        audience: [ProductAudience::Professionals->value],
        goals: [ProductGoal::Learn->value],
        productType: ProductType::Course->value,
        subjectArea: 'Business',
        language: 'English',
    );

    $entry->refresh();

    return [$validation, $product, $entry, $creator];
}

it('publishes validated products to the public directory and links to public verification', function () {
    [$validation] = validatedDirectoryFixture();

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertSee($validation->verification_identifier)
        ->assertSee(route('public.verification.show', $validation->verification_identifier));
});

it('searches and filters directory results using deterministic public criteria', function () {
    [$validation] = validatedDirectoryFixture();

    $this->get(route('public.directory', [
        'q' => 'Directory Product',
        'audience' => ProductAudience::Professionals->value,
        'goal' => ProductGoal::Learn->value,
        'product_type' => ProductType::Course->value,
        'subject_area' => 'Business',
        'language' => 'English',
    ]))
        ->assertOk()
        ->assertSee($validation->verification_identifier);
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

    $identifiers = [
        $firstValidation->verification_identifier,
        $secondValidation->verification_identifier,
    ];

    sort($identifiers);

    $recommendations = app(ProductRecommendations::class)->recommend(limit: 2);

    expect($recommendations->pluck('verificationIdentifier')->all())->toBe($identifiers);
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
        ->assertSee(end($validationIdentifiers));

    $entries = $response->viewData('entries');

    expect($entries->currentPage())->toBe(2)
        ->and($entries->url(2))->toContain('audience='.ProductAudience::Professionals->value);
});

it('does not present suspended validation as currently validated in the directory', function () {
    [$validation, , , $admin] = validatedDirectoryFixture();

    app(\App\Services\ValidationStateTransition::class)->transition(
        validation: $validation,
        to: ValidationStatus::Suspended,
        actor: $admin,
        reason: 'Directory trust review',
    );

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($validation->verification_identifier);
});

it('removes a hidden directory record from public discovery without deleting validation history', function () {
    [$validation] = validatedDirectoryFixture();

    PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->update(['directory_visible' => false]);

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($validation->verification_identifier);

    expect($validation->fresh())->not->toBeNull();
});

it('does not expose internal creator data through the directory projection', function () {
    [$validation, , $entry] = validatedDirectoryFixture();

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertSee($entry->title)
        ->assertSee($validation->verification_identifier)
        ->assertDontSee('internal_creator_id')
        ->assertDontSee('organization_id');
});
