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
 * @property string|null $quoted_price
 * @property int|null $quoted_amount_minor
 * @property int|null $organization_id
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
        'complexity', 'quoted_price', 'quoted_amount_minor', 'currency', 'status', 'submitted_at', 'payment_started_at', 'paid_at',
        'evaluation_started_at', 'cancelled_at', 'refunded_at', 'intake_notes',
    ];

    protected function casts(): array
    {
        return [
            'complexity' => EvaluationComplexity::class,
            'status' => EvaluationRequestStatus::class,
            'quoted_price' => 'decimal:2',
            'quoted_amount_minor' => 'integer',
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

            if ($request->hasDirtyLifecycleFields()) {
                throw new DomainStateTransitionException('Evaluation request lifecycle timestamps can only be set by their domain workflow.');
            }
        });

        static::saving(function (self $request): void {
            $request->assertTenantConsistency();
            $request->assertCommercialAmountConsistency();

            if ($request->exists && $request->isDirty('status')) {
                throw new DomainStateTransitionException('Evaluation request status can only be changed through EvaluationRequestStateTransition.');
            }

            if ($request->exists && $request->hasDirtyLifecycleFields()) {
                throw new DomainStateTransitionException('Evaluation request lifecycle timestamps can only be changed by the domain workflow.');
            }

            $originalStatus = $request->exists ? $request->getRawOriginal('status') : null;
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
                    'quoted_amount_minor',
                    'currency',
                ];

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

    private function assertTenantConsistency(): void
    {
        if ($this->organization_id === null || $this->product_id === null) {
            return;
        }

        $productOrganizationId = Product::query()
            ->whereKey($this->product_id)
            ->value('organization_id');

        if ($productOrganizationId !== $this->organization_id) {
            throw new DomainStateTransitionException(
                'An evaluation request product must belong to the requested organization.',
            );
        }

        if ($this->product_release_id === null) {
            return;
        }

        $releaseProductId = ProductRelease::query()
            ->whereKey($this->product_release_id)
            ->value('product_id');

        if ($releaseProductId !== $this->product_id) {
            throw new DomainStateTransitionException(
                'An evaluation request product release must belong to the requested product.',
            );
        }
    }

    private function assertCommercialAmountConsistency(): void
    {
        if ($this->quoted_amount_minor === null) {
            if ($this->quoted_price !== null) {
                throw new DomainStateTransitionException('A quoted price must be represented by an integer minor-unit amount.');
            }

            return;
        }

        if ($this->quoted_amount_minor <= 0) {
            throw new DomainStateTransitionException('A quoted amount must be positive.');
        }

        if ($this->service_package_id === null) {
            throw new DomainStateTransitionException('A quoted amount requires a service package.');
        }

        $package = ServicePackage::query()->find($this->service_package_id);
        if ($package === null) {
            throw new DomainStateTransitionException('A quoted amount requires an existing service package.');
        }

        if ($this->quoted_amount_minor !== $package->price_minor) {
            throw new DomainStateTransitionException('The quoted amount must match the selected service package price.');
        }

        if ($this->currency !== strtoupper($package->currency)) {
            throw new DomainStateTransitionException('The quoted currency must match the selected service package currency.');
        }
    }

    private function hasDirtyLifecycleFields(): bool
    {
        $lifecycleFields = [
            'submitted_at',
            'payment_started_at',
            'paid_at',
            'evaluation_started_at',
            'cancelled_at',
            'refunded_at',
        ];

        return array_intersect(array_keys($this->getDirty()), $lifecycleFields) !== [];
    }
}
