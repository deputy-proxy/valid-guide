<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpertiseArea;
use App\Enums\ProductType;
use App\Services\PublicExpertDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicExpertController extends Controller
{
    public function __construct(
        private readonly PublicExpertDirectory $directory,
    ) {}

    public function index(Request $request): View
    {
        $query = $request->string('q')->trim()->value();
        $expertiseValue = $request->string('expertise')->trim()->value();
        $productTypeValue = $request->string('product_type')->trim()->value();
        $expertise = $expertiseValue !== '' ? ExpertiseArea::tryFrom($expertiseValue) : null;
        $productType = $productTypeValue !== '' ? ProductType::tryFrom($productTypeValue) : null;

        return view('public.pages.experts', [
            'experts' => $this->directory->search(
                query: $query !== '' ? $query : null,
                expertise: $expertise,
                productType: $productType,
            ),
            'query' => $query,
            'expertise' => $expertiseValue,
            'productType' => $productTypeValue,
            'expertiseAreas' => ExpertiseArea::cases(),
            'productTypes' => ProductType::cases(),
        ]);
    }

    public function show(string $slug): View
    {
        $expert = $this->directory->find($slug);

        abort_if($expert === null, 404);

        return view('public.pages.expert', [
            'expert' => $expert,
        ]);
    }
}
