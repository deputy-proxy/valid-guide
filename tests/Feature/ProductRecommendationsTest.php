<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ValidationStatus;
use App\Models\User;
use App\Services\ProductRecommendations;
use App\Services\ProductSuitability;
use App\Services\PublicVerificationPublication;
use App\Services\ValidationIssuance;
use App\Services\ValidationStateTransition;
use Illuminate\Support\Facades\DB;

function recommendationFixture(string $slug, array $audiences = [], array $goals = []): array
{
    [$evaluation, , $creator] = creatorActionEvaluationFixture();
    $product = $evaluation->productRelease->product;
    $release = $evaluation->productRelease;
    $product->update([
        'title' => 'Product '.$slug,
        'slug' => $slug,
        'subject_area' => 'Leadership',
        'language' => 'en',
    ]);
    $release->update(['title_snapshot' => $product->title]);

    app(ProductSuitability::class)->update($creator, $product, [
        'matching_audiences' => $audiences,
        'matching_goals' => $goals,
    ]);

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    $validation = app(ValidationIssuance::class)->issue($evaluation, $admin);

    return [$validation, $product, $admin];
}

it('ranks eligible products by explicit public matching signals and explains the matches', function () {
    [$best] = recommendationFixture('best', [ProductAudience::Professionals->value], [ProductGoal::ProfessionalDevelopment->value]);
    [$second] = recommendationFixture('second', [ProductAudience::Professionals->value]);
    [$third] = recommendationFixture('third');

    $recommendations = app(ProductRecommendations::class)->recommend(
        audience: ProductAudience::Professionals,
        goal: ProductGoal::ProfessionalDevelopment,
        subjectArea: 'Leadership',
        language: 'EN',
    );

    expect($recommendations)->toHaveCount(3)
        ->and($recommendations->pluck('verificationIdentifier')->all())->toBe([
            $best->verification_identifier,
            $second->verification_identifier,
            $third->verification_identifier,
        ])
        ->and($recommendations->first()->score)->toBe(4)
        ->and($recommendations->first()->reasons)->toContain('Matches audience: Professionals.')
        ->and($recommendations->first()->reasons)->toContain('Matches use case: Professional Development.')
        ->and($recommendations->first()->reasons)->toContain('Matches subject area: Leadership.')
        ->and($recommendations->first()->reasons)->toContain('Matches language: EN.')
        ->and($recommendations->first()->reasons)->toContain('Currently validated for release v1.');
});

it('uses deterministic title and verification ordering for equal recommendation scores', function () {
    [$alpha] = recommendationFixture('alpha');
    [$beta] = recommendationFixture('beta');

    $recommendations = app(ProductRecommendations::class)->recommend();

    expect($recommendations->pluck('verificationIdentifier')->all())->toBe([
        $alpha->verification_identifier,
        $beta->verification_identifier,
    ]);
});

it('handles incomplete metadata without failing or inventing a suitability match', function () {
    recommendationFixture('incomplete');

    $recommendations = app(ProductRecommendations::class)->recommend(
        audience: ProductAudience::Professionals,
        goal: ProductGoal::ProfessionalDevelopment,
    );

    expect($recommendations)->toHaveCount(0);
});

it('excludes hidden and non-current validation records from recommendations', function () {
    [$activeValidation] = recommendationFixture('active', [ProductAudience::Professionals->value]);
    [$suspendedValidation, , $admin] = recommendationFixture('suspended', [ProductAudience::Professionals->value]);
    [$hiddenValidation, , $hiddenAdmin] = recommendationFixture('hidden', [ProductAudience::Professionals->value]);

    app(ValidationStateTransition::class)->transition(
        $suspendedValidation,
        ValidationStatus::Suspended,
        $admin,
        'Recommendation regression test suspension.',
    );

    $hiddenRecord = $hiddenValidation->publicVerificationRecord()->firstOrFail();
    app(PublicVerificationPublication::class)->setVisibility($hiddenRecord, false, false, $hiddenAdmin);

    $recommendations = app(ProductRecommendations::class)->recommend(audience: ProductAudience::Professionals);

    expect($recommendations->pluck('verificationIdentifier')->all())->toBe([$activeValidation->verification_identifier]);
});

it('does not expose private or commercial fields in recommendation results', function () {
    [$validation, $product, $creator] = recommendationFixture(
        'private-boundary',
        [ProductAudience::Professionals->value],
    );

    DB::table('products')->where('id', $product->getKey())->update(['reference_price' => 999.99]);

    $recommendation = app(ProductRecommendations::class)->recommend(audience: ProductAudience::Professionals)->first();

    expect($recommendation)->not->toBeNull()
        ->and(get_object_vars($recommendation))->not->toHaveKey('email')
        ->and(get_object_vars($recommendation))->not->toHaveKey('reference_price')
        ->and(get_object_vars($recommendation))->not->toHaveKey('matching_audiences')
        ->and($recommendation?->verificationIdentifier)->toBe($validation->verification_identifier)
        ->and($creator->email)->not->toBe($recommendation?->verificationIdentifier);
});

it('renders recommendation explanations and verification links on the public directory', function () {
    [$validation, $product] = recommendationFixture(
        'directory-recommendation',
        [ProductAudience::Professionals->value],
        [ProductGoal::ProfessionalDevelopment->value],
    );

    $response = $this->get(route('public.directory', [
        'audience' => ProductAudience::Professionals->value,
        'goal' => ProductGoal::ProfessionalDevelopment->value,
    ]));

    $response->assertOk()
        ->assertSee('Recommended based on these criteria')
        ->assertSee('Why this is recommended')
        ->assertSee('Matches audience: Professionals.')
        ->assertSee('Matches use case: Professional Development.')
        ->assertSee('Currently validated for release v1.')
        ->assertSee($product->title)
        ->assertSee(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]), false)
        ->assertDontSee('guaranteed')
        ->assertDontSee('best product');
});

it('renders a safe directory state when no validated recommendations exist', function () {
    $response = $this->get(route('public.directory'));

    $response->assertOk()
        ->assertDontSee('Recommended based on these criteria')
        ->assertSee('No matching products');
});
