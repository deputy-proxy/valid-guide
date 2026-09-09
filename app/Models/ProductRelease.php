<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductReleaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'edition', 'version', 'published_at', 'release_identifier',
        'product_url_snapshot', 'title_snapshot', 'quantitative_metadata',
        'material_change_notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'quantitative_metadata' => 'array',
            'status' => ProductReleaseStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $release): void {
            $originalStatus = $release->getOriginal('status');

            if ($originalStatus !== ProductReleaseStatus::Draft->value) {
                $allowed = ['status', 'published_at'];

                if (array_diff(array_keys($release->getDirty()), $allowed)) {
                    throw new DomainStateTransitionException(
                        'A published product release is immutable. Create a new release for material changes.',
                    );
                }
            }
        });

        static::deleting(function (self $release): void {
            if ($release->status !== ProductReleaseStatus::Draft) {
                throw new DomainStateTransitionException('Published product releases cannot be deleted.');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class);
    }
}
