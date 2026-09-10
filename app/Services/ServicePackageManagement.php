<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationComplexity;
use App\Enums\ProductType;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ServicePackageManagement
{
    /** @phpstan-param array<string, mixed> $attributes */
    public function create(User $user, array $attributes): ServicePackage
    {
        Gate::forUser($user)->authorize('create', ServicePackage::class);

        $validated = Validator::make($attributes, $this->rules())->validate();
        $validated['currency'] = strtoupper((string) $validated['currency']);
        $validated['status'] = 'active';

        $package = ServicePackage::query()->create($validated);

        AuditLogger::record(
            event: 'service_package.created',
            auditable: $package,
            after: $this->auditData($package),
            actor: $user,
        );

        return $package->refresh();
    }

    /** @phpstan-param array<string, mixed> $attributes */
    public function update(User $user, ServicePackage $package, array $attributes): ServicePackage
    {
        Gate::forUser($user)->authorize('update', $package);

        $validated = Validator::make($attributes, $this->rules($package))->validate();
        $validated['currency'] = strtoupper((string) $validated['currency']);
        unset($validated['status']);

        $before = $this->auditData($package);
        $package->fill($validated);
        $package->save();

        AuditLogger::record(
            event: 'service_package.updated',
            auditable: $package,
            before: $before,
            after: $this->auditData($package),
            actor: $user,
        );

        return $package->refresh();
    }

    public function archive(User $user, ServicePackage $package): ServicePackage
    {
        Gate::forUser($user)->authorize('archive', $package);

        ServicePackage::query()
            ->whereKey($package->getKey())
            ->update(['status' => 'inactive']);

        AuditLogger::record(
            event: 'service_package.archived',
            auditable: $package->refresh(),
            before: ['status' => 'active'],
            after: ['status' => 'inactive'],
            actor: $user,
        );

        return $package;
    }

    /** @phpstan-return array<string, array<int, mixed>> */
    private function rules(?ServicePackage $package = null): array
    {
        $slugRule = Rule::unique('service_packages', 'slug');

        if ($package !== null) {
            $slugRule = $slugRule->ignore($package->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', $slugRule],
            'description' => ['required', 'string'],
            'product_types' => ['required', 'array', 'min:1'],
            'product_types.*' => ['required', Rule::enum(ProductType::class)],
            'complexity_levels' => ['required', 'array', 'min:1'],
            'complexity_levels.*' => ['required', Rule::enum(EvaluationComplexity::class)],
            'price_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
        ];
    }

    /** @return array<string, mixed> */
    private function auditData(ServicePackage $package): array
    {
        return [
            'name' => $package->name,
            'slug' => $package->slug,
            'product_types' => $package->product_types,
            'complexity_levels' => $package->complexity_levels,
            'price_minor' => $package->price_minor,
            'currency' => $package->currency,
            'status' => $package->status,
        ];
    }
}
