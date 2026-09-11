<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ProductType $product_type
 * @property ValidationStatus $validation_status
 * @property array<int|string, mixed>|null $matching_audiences
 * @property array<int|string, mixed>|null $matching_goals
 * @property CarbonImmutable|null $issued_at
 */
class PublicDirectoryEntry extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'public_verification_record_id',
        'verification_identifier',
        'title',
        'slug',
        'creator_name',
        'product_type',
        'subject_area',
        'language',
        'matching_audiences',
        'matching_goals',
        'validation_status',
        'release_identifier',
        'issued_at',
        'directory_visible',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'matching_audiences' => 'array',
            'matching_goals' => 'array',
            'validation_status' => ValidationStatus::class,
            'issued_at' => 'datetime',
            'directory_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<PublicVerificationRecord, $this> */
    public function publicVerificationRecord(): BelongsTo
    {
        return $this->belongsTo(PublicVerificationRecord::class);
    }
}
