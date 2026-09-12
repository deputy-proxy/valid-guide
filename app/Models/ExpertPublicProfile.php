<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpertPublicProfileStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ExpertPublicProfileStatus $status
 * @property list<string>|null $expertise_areas
 * @property list<string>|null $product_types
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $removed_at
 */
class ExpertPublicProfile extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_profile_id',
        'slug',
        'display_name',
        'bio',
        'credentials',
        'expertise_areas',
        'product_types',
        'status',
        'published_at',
        'suspended_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'expertise_areas' => 'array',
            'product_types' => 'array',
            'status' => ExpertPublicProfileStatus::class,
            'published_at' => 'datetime',
            'suspended_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function auditorProfile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class);
    }
}
