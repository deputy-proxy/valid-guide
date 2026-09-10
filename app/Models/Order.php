<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property OrderStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $provider
 * @property string|null $provider_reference
 */
class Order extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'evaluation_request_id',
        'amount_minor',
        'currency',
        'status',
        'provider',
        'provider_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => OrderStatus::class,
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<EvaluationRequest, $this> */
    public function evaluationRequest(): BelongsTo
    {
        return $this->belongsTo(EvaluationRequest::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Refund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }
}
