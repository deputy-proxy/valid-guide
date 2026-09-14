<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\StandardVersion;
use App\Models\User;
use App\Services\CalibrationQualityMeasurement;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class CalibrationQualityPage extends Page
{
    protected string $view = 'filament.pages.calibration-quality';

    protected static string|UnitEnum|null $navigationGroup = 'Governance';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Calibration';

    protected static ?string $slug = 'calibration';

    protected static ?string $title = 'Methodology Calibration';

    /** @var array<int, array<string, mixed>> */
    public array $measurements = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isPlatformAdmin();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->loadMeasurements();
    }

    public function recordReview(int $standardVersionId): void
    {
        $actor = self::authenticatedUser();
        $standardVersion = StandardVersion::query()->findOrFail($standardVersionId);

        app(CalibrationQualityMeasurement::class)->recordReview($standardVersion, $actor);

        Notification::make()
            ->success()
            ->title('Calibration review recorded')
            ->send();
    }

    private function loadMeasurements(): void
    {
        $service = app(CalibrationQualityMeasurement::class);

        $this->measurements = StandardVersion::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (StandardVersion $version): array => $service->forStandardVersion($version))
            ->all();
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
