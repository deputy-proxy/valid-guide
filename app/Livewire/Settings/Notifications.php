<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\NotificationCategory;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Notification settings')]
final class Notifications extends Component
{
    /** @var array<string, bool> */
    public array $preferences = [];

    public function mount(): void
    {
        $user = $this->authenticatedUser();

        foreach (NotificationCategory::cases() as $category) {
            $this->preferences[$category->value] = $user->canReceiveNotification($category);
        }
    }

    public function save(): void
    {
        $user = $this->authenticatedUser();

        foreach (NotificationCategory::cases() as $category) {
            $user->setNotificationPreference($category, (bool) ($this->preferences[$category->value] ?? true));
        }

        Flux::toast(variant: 'success', text: __('Notification preferences updated.'));
    }

    /** @return array<int, array{key:string,label:string}> */
    public function categories(): array
    {
        return array_map(
            static fn (NotificationCategory $category): array => [
                'key' => $category->value,
                'label' => ucfirst($category->value),
            ],
            NotificationCategory::cases(),
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Authentication is required.');
        }

        return $user;
    }
}
