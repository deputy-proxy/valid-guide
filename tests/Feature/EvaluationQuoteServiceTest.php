<?php

declare(strict_types=1);

use App\Enums\EvaluationComplexity;
use App\Enums\ProductType;
use App\Models\ServicePackage;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationQuoteService;

function quotePackage(): ServicePackage
{
    return ServicePackage::create([
        'name' => 'Standard Evaluation',
        'slug' => 'quote-package-'.uniqid(),
        'description' => 'Independent evaluation.',
        'product_types' => ['course', 'guide'],
        'complexity_levels' => ['simple', 'standard'],
        'price_minor' => 50000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);
}

test('resolves a deterministic quote from package product type and complexity', function () {
    $package = quotePackage();

    $quote = app(EvaluationQuoteService::class)->quote(
        $package,
        ProductType::Course,
        EvaluationComplexity::Standard,
    );

    expect($quote->amountMinor)->toBe(50000)
        ->and($quote->currency)->toBe('EUR')
        ->and($quote->servicePackageId)->toBe($package->id)
        ->and($quote->complexity)->toBe(EvaluationComplexity::Standard);
});

test('rejects unsupported product types', function () {
    expect(fn () => app(EvaluationQuoteService::class)->quote(
        quotePackage(),
        ProductType::Membership,
        EvaluationComplexity::Standard,
    ))->toThrow(DomainStateTransitionException::class);
});

test('rejects unsupported complexity', function () {
    expect(fn () => app(EvaluationQuoteService::class)->quote(
        quotePackage(),
        ProductType::Course,
        EvaluationComplexity::Complex,
    ))->toThrow(DomainStateTransitionException::class);
});

test('rejects inactive packages', function () {
    $package = quotePackage();
    $package->status = 'inactive';
    $package->save();

    expect(fn () => app(EvaluationQuoteService::class)->quote(
        $package,
        ProductType::Course,
        EvaluationComplexity::Standard,
    ))->toThrow(DomainStateTransitionException::class);
});
