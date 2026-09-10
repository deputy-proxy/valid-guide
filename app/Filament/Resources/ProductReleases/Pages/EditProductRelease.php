<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductReleases\Pages;

use App\Filament\Resources\ProductReleases\ProductReleaseResource;
use App\Models\ProductRelease;
use App\Models\User;
use App\Services\ProductReleaseManagement;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditProductRelease extends EditRecord
{
    protected static string $resource = ProductReleaseResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless($record instanceof ProductRelease, 404);

        return app(ProductReleaseManagement::class)->update($user, $record, $data);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Product release updated';
    }
}
