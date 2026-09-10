<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

final class UiFoundation extends Page
{
    protected string $view = 'filament.pages.ui-foundation';

    protected static ?string $slug = 'ui-foundation';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'UI Foundation';
    }
}
