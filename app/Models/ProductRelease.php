<?php

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
