<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\StandardVersion;
use App\Models\User;
use App\Services\ValidationCalibrationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class ValidationCalibrationPage extends Page
{
    protected string $view = 'filament.pages.validation-calibration';

    protected static string|UnitEnum|null $navigationGroup = 'Validation';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Calibration';

    protected static ?string $slug = 'validation-calibration';

    protected static ?string $title = 'Validation Calibration';

    /** @var list<array<string,mixed>> */
    public array $reports = [];

    public function mount(): void
    {
        $this->reports = app(ValidationCalibrationService::class)->report();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isPlatformAdmin();
    }

    public function recordReview(int $standardVersionId): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->isPlatformAdmin(), 403);

        $standardVersion = StandardVersion::query()->findOrFail($standardVersionId);
        app(ValidationCalibrationService::class)->recordReview($standardVersion, $actor);

        Notification::make()
            ->success()
            ->title('Calibration review recorded')
            ->send();
    }
}
