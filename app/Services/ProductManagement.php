<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductManagement
{
    /**
     * @param array<string, mixed>  $attributes
     */
    public function create(User $user, Organization $organization, array $attributes): Product
    {
        Gate::forUser($user)->authorize('create', [Product::class, $organization]);

        $validated = Validator::make($attributes, $this->rules($organization))->validate();

        $validated['organization_id'] = $organization->getKey();
        $validated['status'] = ProductStatus::Active->value;

        /** @var Product */
        return Product::query()->create($validated);
    }

    /**
     * @param array<string, mixed>  $attributes
     */
    public function update(User $user, Product $product, array $attributes): Product
    {
        Gate::forUser($user)->authorize('update', $product);

        $validated = Validator::make($attributes, $this->rules($product->organization, $product))->validate();

        unset($validated['organization_id'], $validated['status']);

        $product->fill($validated);
        $product->save();

        return $product->refresh();
    }

    public function archive(User $user, Product $product): Product
    {
        Gate::forUser($user)->authorize('archive', $product);

        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedProduct->status !== ProductStatus::Active) {
                throw new DomainStateTransitionException('Only active products can be archived.');
            }

            Product::query()
                ->whereKey($lockedProduct->getKey())
                ->update(['status' => ProductStatus::Archived->value]);
        });

        return $product->refresh();
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(Organization $organization, ?Product $product = null): array
    {
        $slugRule = Rule::unique('products', 'slug')
            ->where(fn (Builder $query): Builder => $query->where('organization_id', $organization->getKey()));

        if ($product !== null) {
            $slugRule = $slugRule->ignore($product->getKey());
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', $slugRule],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'subject_area' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'canonical_url' => ['required', 'url', 'max:2048'],
            'url' => ['nullable', 'url', 'max:2048'],
            'reference_price' => ['nullable', 'numeric', 'min:0'],
            'reference_currency' => ['nullable', 'string', 'size:3'],
            'target_audience' => ['required', 'string'],
            'claimed_outcomes' => ['required', 'array', 'min:1'],
            'claimed_outcomes.*' => ['required', 'string', 'max:1000'],
            'language' => ['required', 'string', 'max:16'],
        ];
    }
}
