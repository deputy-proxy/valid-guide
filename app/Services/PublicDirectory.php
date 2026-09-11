<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\PublicDirectoryEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicDirectory
{
    public function search(
        ?string $query = null,
        ?ProductAudience $audience = null,
        ?ProductGoal $goal = null,
        ?ProductType $productType = null,
        ?string $subjectArea = null,
        ?string $language = null,
    ): LengthAwarePaginator {
        $builder = PublicDirectoryEntry::query()
            ->where('directory_visible', true)
            ->where('validation_status', ValidationStatus::Active->value);

        $query = $query !== null ? trim($query) : null;
        $subjectArea = $subjectArea !== null ? trim($subjectArea) : null;
        $language = $language !== null ? trim($language) : null;

        if ($query !== null && $query !== '') {
            $builder->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('creator_name', 'like', '%'.$query.'%')
                    ->orWhere('subject_area', 'like', '%'.$query.'%');
            });
        }

        if ($audience !== null) {
            $builder->whereJsonContains('matching_audiences', $audience->value);
        }

        if ($goal !== null) {
            $builder->whereJsonContains('matching_goals', $goal->value);
        }

        if ($productType !== null) {
            $builder->where('product_type', $productType->value);
        }

        if ($subjectArea !== null && $subjectArea !== '') {
            $builder->whereRaw('lower(subject_area) = ?', [mb_strtolower($subjectArea)]);
        }

        if ($language !== null && $language !== '') {
            $builder->whereRaw('lower(language) = ?', [mb_strtolower($language)]);
        }

        return $builder
            ->orderBy('title')
            ->orderBy('verification_identifier')
            ->paginate(12)
            ->withQueryString();
    }
}
