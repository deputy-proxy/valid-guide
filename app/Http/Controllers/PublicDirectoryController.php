<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductType;
use App\Services\ProductRecommendations;
use App\Services\PublicDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDirectoryController extends Controller
{
    public function __construct(
        private readonly PublicDirectory $directory,
        private readonly ProductRecommendations $recommendations,
    ) {}

    public function index(Request $request): View
    {
        $query = $request->string('q')->trim()->value();
        $audienceValue = $request->string('audience')->trim()->value();
        $goalValue = $request->string('goal')->trim()->value();
        $productTypeValue = $request->string('product_type')->trim()->value();
        $subjectArea = $request->string('subject_area')->trim()->value();
        $language = $request->string('language')->trim()->value();
        $audience = $audienceValue !== '' ? ProductAudience::tryFrom($audienceValue) : null;
        $goal = $goalValue !== '' ? ProductGoal::tryFrom($goalValue) : null;
        $productType = $productTypeValue !== '' ? ProductType::tryFrom($productTypeValue) : null;
        $subject = $subjectArea !== '' ? $subjectArea : null;
        $selectedLanguage = $language !== '' ? $language : null;

        return view('public.pages.directory', [
            'entries' => $this->directory->search(
                query: $query !== '' ? $query : null,
                audience: $audience,
                goal: $goal,
                productType: $productType,
                subjectArea: $subject,
                language: $selectedLanguage,
            ),
            'recommendations' => $this->recommendations->recommend(
                audience: $audience,
                goal: $goal,
                productType: $productType,
                subjectArea: $subject,
                language: $selectedLanguage,
                query: $query !== '' ? $query : null,
            ),
            'query' => $query,
            'audience' => $audienceValue,
            'goal' => $goalValue,
            'productType' => $productTypeValue,
            'subjectArea' => $subjectArea,
            'language' => $language,
            'audiences' => ProductAudience::cases(),
            'goals' => ProductGoal::cases(),
            'productTypes' => ProductType::cases(),
        ]);
    }
}
