<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ProductReleaseManagement
{
    /** @phpstan-param array<string, mixed> $attributes */
    public function create(User $actor, Product $product, array $attributes): ProductRelease
    {
        Gate::forUser($actor)->authorize('create', [ProductRelease::class, $product]);

        $validated = Validator::make($attributes, $this->rules($product))->validate();
        $validated['product_id'] = $product->getKey();

        return ProductRelease::query()->create($validated);
    }

    /** @phpstan-param array<string, mixed> $attributes */
    public function update(User $actor, ProductRelease $release, array $attributes): ProductRelease
    {
        Gate::forUser($actor)->authorize('update', $release);

        $validated = Validator::make($attributes, $this->rules($release->product, $release))->validate();
        unset($validated['product_id'], $validated['status'], $validated['published_at']);

        $release->fill($validated);
        $release->save();

        return $release->refresh();
    }

    /** @phpstan-return array<string, array<int, mixed>> */
    private function rules(Product $product, ?ProductRelease $release = null): array
    {
        $identifierRule = Rule::unique('product_releases', 'release_identifier')
            ->where(fn (Builder $query): Builder => $query->where('product_id', $product->getKey()));

        if ($release !== null) {
            $identifierRule = $identifierRule->ignore($release->getKey());
        }

        return [
            'edition' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'release_identifier' => ['required', 'string', 'max:255', $identifierRule],
            'product_url_snapshot' => ['nullable', 'url', 'max:2048'],
            'title_snapshot' => ['required', 'string', 'max:255'],
            'quantitative_metadata' => ['nullable', 'array'],
            'material_change_notes' => ['nullable', 'string'],
        ];
    }
}
