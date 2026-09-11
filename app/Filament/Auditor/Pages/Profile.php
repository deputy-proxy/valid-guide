<?php

declare(strict_types=1);

namespace App\Filament\Auditor\Pages;

use App\Models\User;
use App\Services\AuditorProfileManagement;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

final class Profile extends Page
{
    protected string $view = 'filament.auditor.pages.profile';

    protected static ?string $slug = 'profile';

    protected static ?string $navigationLabel = 'My Profile';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $title = 'My Auditor Profile';

    public string $bio = '';

    public string $credentials = '';

    public string $formatExperience = '';

    public bool $methodologyLiterate = false;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to access this profile.');
        }

        $profile = $user->auditorProfile;

        if ($profile === null) {
            throw new AuthorizationException('An Auditor profile is required.');
        }

        $this->bio = $profile->bio ?? '';
        $this->credentials = $profile->credentials ?? '';
        $this->formatExperience = implode(', ', $profile->format_experience ?? []);
        $this->methodologyLiterate = $profile->methodology_literate;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You are not authorized to update this profile.');
        }

        $formatExperience = array_values(array_filter(
            array_map('trim', explode(',', $this->formatExperience)),
            static fn (string $value): bool => $value !== '',
        ));

        app(AuditorProfileManagement::class)->update(
            $user,
            $this->bio,
            $this->credentials,
            $formatExperience,
            $this->methodologyLiterate,
        );

        Notification::make()
            ->title('Profile updated')
            ->success()
            ->send();
    }
}
