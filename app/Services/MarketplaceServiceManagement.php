<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertiseArea;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\MarketplaceServiceStatus;
use App\Enums\ProductType;
use App\Models\AuditorProfile;
use App\Models\MarketplaceService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class MarketplaceServiceManagement
{
    /** @phpstan-param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): MarketplaceService
    {
        Gate::forUser($actor)->authorize('create', MarketplaceService::class);

        $validated = Validator::make($attributes, $this->rules())->validate();
        $profile = $this->eligibleProfile($actor);

        $validated['auditor_profile_id'] = $profile->getKey();
        $validated['currency'] = strtoupper((string) $validated['currency']);
        $validated['status'] = MarketplaceServiceStatus::Draft->value;

        $service = MarketplaceService::query()->create($validated);

        AuditLogger::record(
            event: 'marketplace_service.created',
            auditable: $service,
            after: $this->auditData($service),
            actor: $actor,
        );

        return $service->refresh();
    }

    /** @phpstan-param array<string, mixed> $attributes */
    public function update(User $actor, MarketplaceService $service, array $attributes): MarketplaceService
    {
        Gate::forUser($actor)->authorize('update', $service);

        if ($service->status !== MarketplaceServiceStatus::Draft) {
            throw new DomainStateTransitionException('Only draft marketplace services can be edited.');
        }

        $validated = Validator::make($attributes, $this->rules($service))->validate();
        $validated['currency'] = strtoupper((string) $validated['currency']);
        unset($validated['status']);

        $before = $this->auditData($service);
        $service->fill($validated)->save();

        AuditLogger::record(
            event: 'marketplace_service.updated',
            auditable: $service,
            before: $before,
            after: $this->auditData($service),
            actor: $actor,
        );

        return $service->refresh();
    }

    public function publish(User $actor, MarketplaceService $service): MarketplaceService
    {
        Gate::forUser($actor)->authorize('publish', $service);
        $this->eligibleProfile($actor);

        return DB::transaction(function () use ($actor, $service): MarketplaceService {
            $service = MarketplaceService::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($service->status !== MarketplaceServiceStatus::Draft) {
                throw new DomainStateTransitionException('Only draft marketplace services can be published.');
            }

            if (($service->expertise_areas ?? []) === [] && ($service->product_types ?? []) === []) {
                throw new DomainStateTransitionException('A marketplace service must define at least one discovery taxonomy.');
            }

            $service->forceFill([
                'status' => MarketplaceServiceStatus::Published,
                'published_at' => now(),
                'paused_at' => null,
            ])->save();

            AuditLogger::record(
                event: 'marketplace_service.published',
                auditable: $service,
                after: ['status' => MarketplaceServiceStatus::Published->value],
                actor: $actor,
            );

            return $service->refresh();
        });
    }

    public function pause(User $actor, MarketplaceService $service): MarketplaceService
    {
        Gate::forUser($actor)->authorize('pause', $service);

        return DB::transaction(function () use ($actor, $service): MarketplaceService {
            $service = MarketplaceService::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($service->status !== MarketplaceServiceStatus::Published) {
                throw new DomainStateTransitionException('Only published marketplace services can be paused.');
            }

            $service->forceFill(['status' => MarketplaceServiceStatus::Paused, 'paused_at' => now()])->save();
            AuditLogger::record(event: 'marketplace_service.paused', auditable: $service, after: ['status' => MarketplaceServiceStatus::Paused->value], actor: $actor);

            return $service->refresh();
        });
    }

    public function archive(User $actor, MarketplaceService $service): MarketplaceService
    {
        Gate::forUser($actor)->authorize('archive', $service);

        return DB::transaction(function () use ($actor, $service): MarketplaceService {
            $service = MarketplaceService::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($service->status === MarketplaceServiceStatus::Archived) {
                throw new DomainStateTransitionException('The marketplace service is already archived.');
            }

            $service->forceFill(['status' => MarketplaceServiceStatus::Archived, 'archived_at' => now()])->save();
            AuditLogger::record(event: 'marketplace_service.archived', auditable: $service, after: ['status' => MarketplaceServiceStatus::Archived->value], actor: $actor);

            return $service->refresh();
        });
    }

    private function eligibleProfile(User $actor): AuditorProfile
    {
        $profile = AuditorProfile::query()
            ->where('auditor_id', $actor->getKey())
            ->where('status', AuditorProfileStatus::Approved->value)
            ->whereHas('expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            })
            ->whereHas('expertPublicProfile', function (Builder $builder): void {
                $builder->where('status', ExpertPublicProfileStatus::Published->value);
            })
            ->first();

        if ($profile === null) {
            throw new DomainStateTransitionException('Only eligible published Experts can use the marketplace.');
        }

        return $profile;
    }

    /** @phpstan-return array<string, array<int, mixed>> */
    private function rules(?MarketplaceService $service = null): array
    {
        $slugRule = Rule::unique('marketplace_services', 'slug');

        if ($service !== null) {
            $slugRule = $slugRule->ignore($service->getKey());
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', $slugRule],
            'description' => ['required', 'string'],
            'expertise_areas' => ['nullable', 'array'],
            'expertise_areas.*' => ['required', Rule::enum(ExpertiseArea::class)],
            'product_types' => ['nullable', 'array'],
            'product_types.*' => ['required', Rule::enum(ProductType::class)],
            'price_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
        ];
    }

    /** @return array<string, mixed> */
    private function auditData(MarketplaceService $service): array
    {
        return [
            'title' => $service->title,
            'slug' => $service->slug,
            'price_minor' => $service->price_minor,
            'currency' => $service->currency,
            'status' => $service->status,
        ];
    }
}
