<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

final class CreatorEvaluationRequestIntake
{
    public function __construct(
        private readonly OrganizationContext $organizationContext,
        private readonly EvaluationRequestCommercialTerms $commercialTerms,
        private readonly EvaluationRequestStateTransition $stateTransition,
    ) {}

    public function start(User $actor, int|string $organizationId, int|string|null $requestId = null): EvaluationRequest
    {
        $organization = $this->organizationContext->resolve($actor, $organizationId);
        Gate::forUser($actor)->authorize('create', [EvaluationRequest::class, $organization]);

        if ($requestId !== null) {
            $request = EvaluationRequest::query()->findOrFail($requestId);
            Gate::forUser($actor)->authorize('view', $request);
            Gate::forUser($actor)->authorize('update', $request);

            return $request->load(['product', 'productRelease', 'materials', 'servicePackage']);
        }

        $existingDraft = EvaluationRequest::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', EvaluationRequestStatus::Draft->value)
            ->whereNull('product_id')
            ->latest('created_at')
            ->first();

        if ($existingDraft !== null) {
            return $existingDraft->load(['product', 'productRelease', 'materials', 'servicePackage']);
        }

        return EvaluationRequest::query()->create([
            'organization_id' => $organization->getKey(),
            'status' => EvaluationRequestStatus::Draft,
        ]);
    }

    public function selectProduct(User $actor, EvaluationRequest $request, Product $product): EvaluationRequest
    {
        $this->authorizeDraft($actor, $request);

        $organizationId = $request->organization_id;
        if ($organizationId === null || $product->organization_id !== $organizationId) {
            throw new DomainStateTransitionException('The selected product does not belong to the request organization.');
        }

        Gate::forUser($actor)->authorize('view', $product);

        $request->product_id = $product->getKey();
        $request->product_release_id = null;
        $this->clearCommercialTerms($request);
        $request->save();

        return $request->refresh()->load(['product', 'productRelease', 'materials', 'servicePackage']);
    }

    public function selectRelease(User $actor, EvaluationRequest $request, ProductRelease $release): EvaluationRequest
    {
        $this->authorizeDraft($actor, $request);

        $product = $request->product;
        if ($product === null || $release->product_id !== $product->getKey()) {
            throw new DomainStateTransitionException('The selected product release does not belong to the request product.');
        }

        if ($release->status->value !== 'current') {
            throw new DomainStateTransitionException('Only the current product release can be submitted for evaluation.');
        }

        $request->product_release_id = $release->getKey();
        $this->clearCommercialTerms($request);
        $request->save();

        return $request->refresh()->load(['product', 'productRelease', 'materials', 'servicePackage']);
    }

    public function updateScope(
        User $actor,
        EvaluationRequest $request,
        string $scope,
        ?string $completionWindow = null,
    ): EvaluationRequest {
        $this->authorizeDraft($actor, $request);

        $scope = trim($scope);
        $completionWindow = $completionWindow !== null ? trim($completionWindow) : null;

        if ($scope === '') {
            throw new DomainStateTransitionException('Evaluation scope is required.');
        }

        if (Str::length($scope) > 5000) {
            throw new DomainStateTransitionException('Evaluation scope must not exceed 5000 characters.');
        }

        if ($completionWindow !== null && Str::length($completionWindow) > 255) {
            throw new DomainStateTransitionException('The requested completion window must not exceed 255 characters.');
        }

        $notes = $this->intakeNotes($request);
        $notes['scope'] = $scope;
        $notes['requested_completion_window'] = $completionWindow;

        $request->intake_notes = json_encode($notes, JSON_THROW_ON_ERROR);
        $request->save();

        return $request->refresh()->load(['product', 'productRelease', 'materials', 'servicePackage']);
    }

    public function confirmClaimsAndAudience(User $actor, EvaluationRequest $request): EvaluationRequest
    {
        $this->authorizeDraft($actor, $request);

        $product = $request->product;
        if ($product === null || $product->organization_id !== $request->organization_id) {
            throw new DomainStateTransitionException('A valid organization product is required before confirming claims and audience.');
        }

        if (blank($product->target_audience) || is_array($product->claimed_outcomes) === false || $product->claimed_outcomes === []) {
            throw new DomainStateTransitionException('The product must have a target audience and claimed outcomes before they can be confirmed.');
        }

        $notes = $this->intakeNotes($request);
        $notes['claims_confirmed'] = true;
        $notes['audience_confirmed'] = true;
        $request->intake_notes = json_encode($notes, JSON_THROW_ON_ERROR);
        $request->save();

        return $request->refresh()->load(['product', 'productRelease', 'materials', 'servicePackage']);
    }

    public function applyCommercialTerms(
        User $actor,
        EvaluationRequest $request,
        int|string $packageId,
        string $complexity,
    ): EvaluationRequest {
        $this->authorizeDraft($actor, $request);

        $product = $request->product;
        if ($product === null || $request->product_release_id === null) {
            throw new DomainStateTransitionException('Select a product and exact current release before choosing commercial terms.');
        }

        $package = ServicePackage::query()
            ->whereKey($packageId)
            ->where('status', 'active')
            ->first();

        if ($package === null) {
            throw new DomainStateTransitionException('The selected service package is not available.');
        }

        return $this->commercialTerms->applyPackage($request, $package, $complexity);
    }

    public function validateForPayment(User $actor, EvaluationRequest $request): EvaluationRequest
    {
        $this->authorizeDraft($actor, $request);
        $request = $request->fresh(['product', 'productRelease', 'materials', 'servicePackage']);

        if ($request === null) {
            throw new RuntimeException('Evaluation request could not be reloaded.');
        }

        $this->assertComplete($request);

        return $this->stateTransition->transition($request, EvaluationRequestStatus::AwaitingPayment, $actor);
    }

    /** @return array<string, mixed> */
    public function intakeNotes(EvaluationRequest $request): array
    {
        if ($request->intake_notes === null || trim($request->intake_notes) === '') {
            return [];
        }

        $decoded = json_decode($request->intake_notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function authorizeDraft(User $actor, EvaluationRequest $request): void
    {
        Gate::forUser($actor)->authorize('update', $request);

        if ($request->status !== EvaluationRequestStatus::Draft) {
            throw new DomainStateTransitionException('Only draft evaluation requests can be edited in the creator wizard.');
        }
    }

    private function clearCommercialTerms(EvaluationRequest $request): void
    {
        $request->service_package_id = null;
        $request->service_package = null;
        $request->service_package_name_snapshot = null;
        $request->service_package_description_snapshot = null;
        $request->service_package_terms_snapshot = null;
        $request->complexity = null;
        $request->quoted_price = null;
        $request->quoted_amount_minor = null;
        $request->currency = null;
    }

    private function assertComplete(EvaluationRequest $request): void
    {
        if ($request->organization_id === null || $request->product_id === null || $request->product_release_id === null) {
            throw new DomainStateTransitionException('An organization, product and exact product release are required.');
        }

        if ($request->product === null || $request->productRelease === null) {
            throw new DomainStateTransitionException('The selected product and release could not be resolved.');
        }

        if ($request->product->organization_id !== $request->organization_id
            || $request->productRelease->product_id !== $request->product_id
            || $request->productRelease->status->value !== 'current') {
            throw new DomainStateTransitionException('The selected product release is no longer eligible for evaluation.');
        }

        $notes = $this->intakeNotes($request);
        if (($notes['claims_confirmed'] ?? false) !== true || ($notes['audience_confirmed'] ?? false) !== true) {
            throw new DomainStateTransitionException('Claims and audience must be confirmed before payment.');
        }

        $scope = $notes['scope'] ?? null;
        if (is_string($scope) === false || trim($scope) === '') {
            throw new DomainStateTransitionException('Evaluation scope is required before payment.');
        }

        if ($request->materials->isEmpty()) {
            throw new DomainStateTransitionException('At least one access or material item is required before payment.');
        }

        if ($request->service_package_id === null || $request->complexity === null || $request->quoted_amount_minor === null || $request->quoted_price === null || blank($request->currency)) {
            throw new DomainStateTransitionException('Complete commercial terms are required before payment.');
        }
    }
}
