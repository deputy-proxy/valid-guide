<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationRequestStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'product_id', 'product_release_id', 'service_package_id', 'service_package',
        'service_package_name_snapshot', 'service_package_description_snapshot', 'service_package_terms_snapshot',
        'complexity', 'quoted_price', 'currency', 'status', 'submitted_at', 'payment_started_at', 'paid_at',
        'evaluation_started_at', 'cancelled_at', 'refunded_at', 'intake_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvaluationRequestStatus::class,
            'quoted_price' => 'decimal:2',
            'service_package_terms_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'payment_started_at' => 'datetime',
            'paid_at' => 'datetime',
            'evaluation_started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            if ($request->product_id !== null && $request->product_release_id !== null) {
                $releaseProductId = ProductRelease::query()
                    ->whereKey($request->product_release_id)
                    ->value('product_id');

                if ($releaseProductId !== (int) $request->product_id) {
                    throw new DomainStateTransitionException(
                        'An evaluation request product release must belong to the requested product.',
                    );
                }
            }

            $originalStatus = $request->exists ? $request->getOriginal('status') : null;
            $frozenStatuses = [
                EvaluationRequestStatus::AwaitingPayment->value,
                EvaluationRequestStatus::Paid->value,
                EvaluationRequestStatus::Intake->value,
                EvaluationRequestStatus::AwaitingCreator->value,
                EvaluationRequestStatus::Ready->value,
                EvaluationRequestStatus::Cancelled->value,
                EvaluationRequestStatus::Refunded->value,
            ];

            if ($originalStatus !== null && in_array($originalStatus, $frozenStatuses, true)) {
                $frozenFields = [
                    'service_package_id',
                    'service_package',
                    'service_package_name_snapshot',
                    'service_package_description_snapshot',
                    'service_package_terms_snapshot',
                    'complexity',
                    'quoted_price',
                    'currency',
                ];

                if (array_intersect(array_keys($request->getDirty()), $frozenFields) !== []) {
                    throw new DomainStateTransitionException(
                        'Commercial terms are immutable after payment processing has started.',
                    );
                }
            }
        });
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

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(EvaluationMaterial::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
