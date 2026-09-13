<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpertiseArea;
use App\Enums\ProductType;
use App\Services\MarketplaceDiscovery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicMarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceDiscovery $discovery) {}

    public function index(Request $request): View
    {
        $query = $request->string('q')->trim()->value();
        $expertiseValue = $request->string('expertise')->trim()->value();
        $productTypeValue = $request->string('product_type')->trim()->value();

        return view('public.pages.marketplace', [
            'services' => $this->discovery->search(
                query: $query !== '' ? $query : null,
                expertise: $expertiseValue !== '' ? ExpertiseArea::tryFrom($expertiseValue) : null,
                productType: $productTypeValue !== '' ? ProductType::tryFrom($productTypeValue) : null,
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
        $service = $this->discovery->find($slug);

        abort_if($service === null, 404);

        return view('public.pages.marketplace-service', ['service' => $service]);
    }
}
