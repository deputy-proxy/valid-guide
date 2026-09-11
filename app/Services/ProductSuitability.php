<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductSuitability
{
    /** @param array<string, mixed> $attributes */
    public function update(User $user, Product $product, array $attributes): Product
    {
        Gate::forUser($user)->authorize('update', $product);

        $validated = Validator::make($attributes, [
            'matching_audiences' => ['nullable', 'array'],
            'matching_audiences.*' => ['required', Rule::enum(ProductAudience::class)],
            'matching_goals' => ['nullable', 'array'],
            'matching_goals.*' => ['required', Rule::enum(ProductGoal::class)],
        ])->validate();

        $product->setAttribute('matching_audiences', json_encode($validated['matching_audiences'] ?? null));
        $product->setAttribute('matching_goals', json_encode($validated['matching_goals'] ?? null));
        $product->save();

        return $product->refresh();
    }
}
