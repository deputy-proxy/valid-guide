<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\PublicDirectoryEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductRecommendations
{
    private const CANDIDATE_LIMIT = 50;

    /**
     * @return Collection<int, ProductRecommendation>
     */
    public function recommend(
        ?ProductAudience $audience = null,
        ?ProductGoal $goal = null,
        ?ProductType $productType = null,
        ?string $subjectArea = null,
        ?string $language = null,
        ?string $query = null,
        int $limit = 3,
        bool $invalidFilter = false,
    ): Collection {
        if ($invalidFilter || $limit <= 0) {
            return collect();
        }

        $query = $query !== null ? trim($query) : null;
        $subjectArea = $subjectArea !== null ? trim($subjectArea) : null;
        $language = $language !== null ? trim($language) : null;
        $hasMatchingCriteria = $audience !== null
            || $goal !== null
            || $productType !== null
            || $subjectArea !== null && $subjectArea !== ''
            || $language !== null && $language !== '';

        $builder = PublicDirectoryEntry::query()
            ->where('directory_visible', true)
            ->where('validation_status', ValidationStatus::Active->value);

        if ($query !== null && $query !== '') {
            $builder->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('creator_name', 'like', '%'.$query.'%')
                    ->orWhere('subject_area', 'like', '%'.$query.'%');
            });
        }

        if ($hasMatchingCriteria) {
            $builder->where(function (Builder $builder) use ($audience, $goal, $productType, $subjectArea, $language): void {
                $hasPreviousCondition = false;

                if ($audience !== null) {
                    $builder->whereJsonContains('matching_audiences', $audience->value);
                    $hasPreviousCondition = true;
                }

                if ($goal !== null) {
                    $method = $hasPreviousCondition ? 'orWhereJsonContains' : 'whereJsonContains';
                    $builder->{$method}('matching_goals', $goal->value);
                    $hasPreviousCondition = true;
                }

                if ($productType !== null) {
                    $method = $hasPreviousCondition ? 'orWhere' : 'where';
                    $builder->{$method}('product_type', $productType->value);
                    $hasPreviousCondition = true;
                }

                if ($subjectArea !== null && $subjectArea !== '') {
                    $method = $hasPreviousCondition ? 'orWhereRaw' : 'whereRaw';
                    $builder->{$method}('lower(subject_area) = ?', [mb_strtolower($subjectArea)]);
                    $hasPreviousCondition = true;
                }

                if ($language !== null && $language !== '') {
                    $method = $hasPreviousCondition ? 'orWhereRaw' : 'whereRaw';
                    $builder->{$method}('lower(language) = ?', [mb_strtolower($language)]);
                }
            });
        }

        $entries = $builder
            ->orderBy('title')
            ->orderBy('verification_identifier')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return $entries
            ->map(fn (PublicDirectoryEntry $entry): ?ProductRecommendation => $this->buildRecommendation(
                $entry,
                $audience,
                $goal,
                $productType,
                $subjectArea,
                $language,
                $query,
                $hasMatchingCriteria,
            ))
            ->filter(fn (?ProductRecommendation $recommendation): bool => $recommendation !== null)
            ->sort(function (ProductRecommendation $left, ProductRecommendation $right): int {
                $score = $right->score <=> $left->score;

                if ($score !== 0) {
                    return $score;
                }

                $title = strcasecmp($left->title, $right->title);

                return $title !== 0
                    ? $title
                    : strcasecmp($left->verificationIdentifier, $right->verificationIdentifier);
            })
            ->take($limit)
            ->values();
    }

    private function buildRecommendation(
        PublicDirectoryEntry $entry,
        ?ProductAudience $audience,
        ?ProductGoal $goal,
        ?ProductType $productType,
        ?string $subjectArea,
        ?string $language,
        ?string $query,
        bool $hasMatchingCriteria,
    ): ?ProductRecommendation {
        $score = 0;
        /** @var list<string> $reasons */
        $reasons = [];

        if ($query !== null && $query !== '' && $this->matchesSearch($entry, $query)) {
            $score++;
            $reasons[] = 'Matches your search.';
        }

        if ($audience !== null && $this->contains($entry->matching_audiences, $audience->value)) {
            $score++;
            $reasons[] = sprintf('Matches audience: %s.', $this->label($audience->value));
        }

        if ($goal !== null && $this->contains($entry->matching_goals, $goal->value)) {
            $score++;
            $reasons[] = sprintf('Matches use case: %s.', $this->label($goal->value));
        }

        if ($productType !== null && $entry->product_type === $productType) {
            $score++;
            $reasons[] = sprintf('Matches product type: %s.', $this->label($productType->value));
        }

        if ($subjectArea !== null && $subjectArea !== '' && $this->sameText($entry->subject_area, $subjectArea)) {
            $score++;
            $reasons[] = sprintf('Matches subject area: %s.', $entry->subject_area);
        }

        if ($language !== null && $language !== '' && $this->sameText($entry->language, $language)) {
            $score++;
            $reasons[] = sprintf('Matches language: %s.', $entry->language);
        }

        if (($hasMatchingCriteria || $query !== null && $query !== '') && $score === 0) {
            return null;
        }

        $reasons[] = sprintf('Currently validated for release %s.', $entry->release_identifier);

        return new ProductRecommendation(
            title: (string) $entry->title,
            slug: (string) $entry->slug,
            productType: $entry->product_type,
            subjectArea: $entry->subject_area,
            language: $entry->language,
            verificationIdentifier: (string) $entry->verification_identifier,
            releaseIdentifier: (string) $entry->release_identifier,
            validationStatus: $entry->validation_status,
            score: $score,
            reasons: $reasons,
        );
    }

    private function matchesSearch(PublicDirectoryEntry $entry, string $query): bool
    {
        $needle = mb_strtolower($query);

        return str_contains(mb_strtolower((string) $entry->title), $needle)
            || str_contains(mb_strtolower((string) $entry->creator_name), $needle)
            || str_contains(mb_strtolower((string) $entry->subject_area), $needle);
    }

    /** @param array<int|string, mixed>|null $values */
    private function contains(?array $values, string $value): bool
    {
        return $values !== null && in_array($value, $values, true);
    }

    private function sameText(?string $left, string $right): bool
    {
        return mb_strtolower(trim((string) $left)) === mb_strtolower(trim($right));
    }

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
