<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\ProductManagement;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $organization = app(OrganizationContext::class)->current($user);

        return app(ProductManagement::class)->create($user, $organization, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Product created';
    }
}
