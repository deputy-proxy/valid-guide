<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property PaymentStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $provider
 * @property string|null $provider_payment_id
 * @property array<string, mixed>|null $provider_metadata
 */
class Payment extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'provider_payment_id',
        'amount_minor',
        'currency',
        'status',
        'paid_at',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasOne<Refund, $this> */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }
}
