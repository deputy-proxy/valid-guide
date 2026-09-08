<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'product_id', 'product_release_id', 'service_package', 'complexity', 'quoted_price',
        'currency', 'status', 'submitted_at', 'payment_started_at', 'paid_at',
        'evaluation_started_at', 'cancelled_at', 'refunded_at', 'intake_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvaluationRequestStatus::class,
            'quoted_price' => 'decimal:2',
            'submitted_at' => 'datetime',
            'payment_started_at' => 'datetime',
            'paid_at' => 'datetime',
            'evaluation_started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
