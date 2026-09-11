<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use App\Services\ActionQueueItem;
use App\Services\RoleActionQueue;
use App\Services\WorkflowNotificationInbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class WorkflowInbox extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $notifications = [];

    /** @var array<int, array<string, bool|int|string>> */
    public array $actions = [];

    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->refreshInbox();
    }

    public function refreshInbox(): void
    {
        $user = $this->authenticatedUser();
        $inbox = app(WorkflowNotificationInbox::class);

        $this->notifications = $inbox->for($user)->all();
        $this->unreadCount = $inbox->unreadCount($user);
        $this->actions = app(RoleActionQueue::class)->for($user)
            ->map(fn (ActionQueueItem $item): array => $item->toArray())
            ->all();
    }

    public function markRead(string $notificationId): void
    {
        app(WorkflowNotificationInbox::class)->markRead($this->authenticatedUser(), $notificationId);
        $this->refreshInbox();
    }

    public function markAllRead(): void
    {
        app(WorkflowNotificationInbox::class)->markAllRead($this->authenticatedUser());
        $this->refreshInbox();
    }

    public function render(): View
    {
        return view('livewire.shared.workflow-inbox');
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
