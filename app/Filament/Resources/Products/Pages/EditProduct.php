<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductManagement;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless($record instanceof Product, 404);

        return app(ProductManagement::class)->update($user, $record, $data);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Product updated';
    }
}
