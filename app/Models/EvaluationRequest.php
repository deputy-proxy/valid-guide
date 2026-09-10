<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationComplexity;
use App\Enums\EvaluationRequestStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property EvaluationRequestStatus|null $status
 * @property EvaluationComplexity $complexity
 * @property float|null $quoted_price
 * @property int|null $product_id
 * @property int|null $product_release_id
 * @property int|null $service_package_id
 * @property array<string, mixed>|null $service_package_terms_snapshot
 */
class EvaluationRequest extends Model
{
    /** @use HasFactory<Factory> */
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
            'complexity' => EvaluationComplexity::class,
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
        static::creating(function (self $request): void {
            if ($request->status !== null && $request->status !== EvaluationRequestStatus::Draft) {
                throw new DomainStateTransitionException('Evaluation requests must be created as drafts and advanced through EvaluationRequestStateTransition.');
            }
            $lifecycleFields = ['submitted_at', 'payment_started_at', 'paid_at', 'evaluation_started_at', 'cancelled_at', 'refunded_at'];
            if (array_intersect(array_keys($request->getDirty()), $lifecycleFields) !== []) {
                throw new DomainStateTransitionException('Evaluation request lifecycle timestamps can only be set by their domain workflow.');
            }
        });
        static::saving(function (self $request): void {
            if ($request->product_id !== null && $request->product_release_id !== null) {
                $releaseProductId = ProductRelease::query()->whereKey($request->product_release_id)->value('product_id');
                if ($releaseProductId !== (int) $request->product_id) {
                    throw new DomainStateTransitionException('An evaluation request product release must belong to the requested product.');
                }
            }
            if ($request->exists && $request->isDirty('status')) {
                throw new DomainStateTransitionException('Evaluation request status can only be changed through EvaluationRequestStateTransition.');
            }
            $lifecycleFields = ['submitted_at', 'payment_started_at', 'paid_at', 'evaluation_started_at', 'cancelled_at', 'refunded_at'];
            if ($request->exists && array_intersect(array_keys($request->getDirty()), $lifecycleFields) !== []) {
                throw new DomainStateTransitionException('Evaluation request lifecycle timestamps can only be changed by the domain workflow.');
            }
            $originalStatus = $request->exists ? $request->getRawOriginal('status') : null;
            $frozenStatuses = [EvaluationRequestStatus::AwaitingPayment->value, EvaluationRequestStatus::Paid->value, EvaluationRequestStatus::Intake->value, EvaluationRequestStatus::AwaitingCreator->value, EvaluationRequestStatus::Ready->value, EvaluationRequestStatus::Cancelled->value, EvaluationRequestStatus::Refunded->value];
            if ($originalStatus !== null && in_array($originalStatus, $frozenStatuses, true)) {
                $frozenFields = ['service_package_id', 'service_package', 'service_package_name_snapshot', 'service_package_description_snapshot', 'service_package_terms_snapshot', 'complexity', 'quoted_price', 'currency'];
                if (array_intersect(array_keys($request->getDirty()), $frozenFields) !== []) {
                    throw new DomainStateTransitionException('Commercial terms are immutable after payment processing has started.');
                }
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductRelease, $this> */
    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    /** @return BelongsTo<ServicePackage, $this> */
    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    /** @return HasMany<EvaluationMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(EvaluationMaterial::class);
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
