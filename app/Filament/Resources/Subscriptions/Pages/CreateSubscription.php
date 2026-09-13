<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\SubscriptionManagement;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->can('create', [Subscription::class, app(OrganizationContext::class)->current($user)]), 403);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $organization = app(OrganizationContext::class)->current($user);
        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);

        return app(SubscriptionManagement::class)->create($user, $organization, $plan);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Subscription created and awaiting payment';
    }
}
