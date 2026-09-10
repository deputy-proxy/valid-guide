<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductReleases\Pages;

use App\Filament\Resources\ProductReleases\ProductReleaseResource;
use App\Models\Product;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProductReleaseManagement;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateProductRelease extends CreateRecord
{
    protected static string $resource = ProductReleaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $organization = app(OrganizationContext::class)->current($user);
        $productId = $data['product_id'] ?? null;

        abort_unless(is_int($productId) || is_string($productId), 422);

        $product = Product::query()
            ->whereKey($productId)
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        unset($data['product_id']);

        return app(ProductReleaseManagement::class)->create($user, $product, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Product release created';
    }
}
