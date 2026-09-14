<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\OperationalMetrics;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class OperationalMetricsPage extends Page
{
    protected string $view = 'filament.pages.operational-metrics';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Operational Metrics';

    protected static ?string $slug = 'operational-metrics';

    protected static ?string $title = 'Operational Metrics';

    /** @var array<string, mixed> */
    public array $metrics = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isPlatformAdmin();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $to = CarbonImmutable::now();
        $from = $to->subDays(30);

        $this->metrics = app(OperationalMetrics::class)->forUser($user, $from, $to);
    }
}
