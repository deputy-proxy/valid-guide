<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductReleaseStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ProductReleaseStatus $status
 * @property CarbonImmutable|null $published_at
 * @property array<int|string, mixed>|null $quantitative_metadata
 */
class ProductRelease extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'edition',
        'version',
        'published_at',
        'release_identifier',
        'product_url_snapshot',
        'title_snapshot',
        'quantitative_metadata',
        'material_change_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'quantitative_metadata' => 'array',
            'status' => ProductReleaseStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $release): void {
            $release->status ??= ProductReleaseStatus::Draft;

            if ($release->status !== ProductReleaseStatus::Draft) {
                throw new DomainStateTransitionException(
                    'Product releases must be created as drafts and published through ProductReleaseStateTransition.',
                );
            }

            if ($release->published_at !== null) {
                throw new DomainStateTransitionException(
                    'A product release publication timestamp can only be set by ProductReleaseStateTransition.',
                );
            }
        });

        static::updating(function (self $release): void {
            if ($release->isDirty('product_id')) {
                throw new DomainStateTransitionException('A product release cannot be moved between products.');
            }

            if ($release->isDirty('status') || $release->isDirty('published_at')) {
                throw new DomainStateTransitionException(
                    'Product release lifecycle fields can only be changed through ProductReleaseStateTransition.',
                );
            }

            if ($release->getRawOriginal('status') !== ProductReleaseStatus::Draft->value) {
                throw new DomainStateTransitionException(
                    'A published product release is immutable. Create a new release for material changes.',
                );
            }
        });

        static::deleting(function (self $release): void {
            if ($release->getRawOriginal('status') !== ProductReleaseStatus::Draft->value) {
                throw new DomainStateTransitionException('Published product releases cannot be deleted.');
            }

            if ($release->evaluations()->exists() || $release->validations()->exists()) {
                throw new DomainStateTransitionException(
                    'Product releases referenced by historical records cannot be deleted.',
                );
            }
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /** @return HasMany<Validation, $this> */
    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class);
    }
}
