<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class CreatorDashboard
{
    /**
     * @return array{
     *     organization: array{id: int|string, name: string, role: string},
     *     products: list<array{id: int|string, title: string, slug: string, status: string, releases: list<array{id: int|string, identifier: string, version: string|null, status: string, published_at: string|null}>>},
     *     evaluation_requests: list<array<string, mixed>>
     * }
     */
    public function forOrganization(User $actor, int|string $organizationId): array
    {
        $organization = Organization::query()->findOrFail($organizationId);
        $role = $this->roleFor($actor, $organization);

        if ($role === null) {
            throw new AuthorizationException('The user is not a member of this organization.');
        }

        $isCreator = in_array($role, [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
            OrganizationRole::Editor->value,
        ], true);

        if ($isCreator) {
            Gate::forUser($actor)->authorize('viewAny', EvaluationRequest::class);
        }

        return [
            'organization' => [
                'id' => $organization->getKey(),
                'name' => (string) $organization->name,
                'role' => $role,
            ],
            'products' => $isCreator ? $this->products($actor, $organization) : [],
            'evaluation_requests' => $this->evaluationRequests($actor, $organization, $role),
        ];
    }

    /**
     * @return list<array{id: int|string, title: string, slug: string, status: string, releases: list<array{id: int|string, identifier: string, version: string|null, status: string, published_at: string|null}>}>
     */
    private function products(User $actor, Organization $organization): array
    {
        return Product::query()
            ->where('organization_id', $organization->getKey())
            ->with('releases')
            ->orderBy('title')
            ->get()
            ->filter(fn (Product $product): bool => Gate::forUser($actor)->allows('view', $product))
            ->map(function (Product $product): array {
                return [
                    'id' => $product->getKey(),
                    'title' => (string) $product->title,
                    'slug' => (string) $product->slug,
                    'status' => $this->rawStatus($product),
                    'releases' => $product->releases
                        ->sortByDesc('published_at')
                        ->values()
                        ->map(fn (ProductRelease $release): array => [
                            'id' => $release->getKey(),
                            'identifier' => (string) $release->release_identifier,
                            'version' => $release->version !== null ? (string) $release->version : null,
                            'status' => $this->rawStatus($release),
                            'published_at' => $release->published_at?->toISOString(),
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function evaluationRequests(User $actor, Organization $organization, string $role): array
    {
        $requests = EvaluationRequest::query()
            ->where('organization_id', $organization->getKey())
            ->with([
                'product',
                'productRelease',
                'servicePackage',
                'order.payments',
                'order.refund',
            ])
            ->latest('created_at')
            ->get();

        $isBilling = $role === OrganizationRole::Billing->value;

        return $requests
            ->filter(fn (EvaluationRequest $request): bool => $isBilling || Gate::forUser($actor)->allows('view', $request))
            ->map(fn (EvaluationRequest $request): array => $this->requestSummary($actor, $request, $isBilling))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function requestSummary(User $actor, EvaluationRequest $request, bool $isBilling): array
    {
        $order = $request->order;
        $payment = $order?->payments->sortByDesc('created_at')->first();
        $refund = $order?->refund;
        $hasDeliveredReport = $request->evaluations()
            ->whereHas('report', fn ($query) => $query->whereNotNull('delivered_at'))
            ->exists();

        $summary = [
            'id' => $request->getKey(),
            'status' => $request->status?->value,
            'product' => $request->product === null ? null : [
                'id' => $request->product->getKey(),
                'title' => (string) $request->product->title,
                'slug' => (string) $request->product->slug,
            ],
            'product_release' => $request->productRelease === null ? null : [
                'id' => $request->productRelease->getKey(),
                'identifier' => (string) $request->productRelease->release_identifier,
                'version' => $request->productRelease->version !== null ? (string) $request->productRelease->version : null,
            ],
            'stage' => $this->stage($request),
            'next_action' => $this->nextAction(
                $actor,
                $request,
                $isBilling,
                $order?->status,
                $payment?->status,
                $refund?->status,
                $hasDeliveredReport,
            ),
            'intake' => $isBilling ? null : $this->intakeStatus($request),
            'commerce' => [
                'package' => $request->service_package_name_snapshot,
                'complexity' => $request->complexity?->value,
                'amount_minor' => $request->quoted_amount_minor,
                'currency' => $request->currency,
                'payment_status' => $payment?->status?->value,
                'refund_eligible' => $this->refundEligible(
                    $actor,
                    $request,
                    $order?->status,
                    $payment?->status,
                    $refund?->status,
                    $hasDeliveredReport,
                ),
            ],
        ];

        if ($isBilling) {
            unset($summary['product'], $summary['product_release'], $summary['intake']);
        }

        return $summary;
    }

    private function stage(EvaluationRequest $request): string
    {
        return match ($request->status) {
            EvaluationRequestStatus::Draft => 'draft',
            EvaluationRequestStatus::AwaitingPayment => 'payment',
            EvaluationRequestStatus::Paid,
            EvaluationRequestStatus::Intake,
            EvaluationRequestStatus::AwaitingCreator,
            EvaluationRequestStatus::Ready => 'intake',
            EvaluationRequestStatus::Cancelled => 'cancelled',
            EvaluationRequestStatus::Refunded => 'refunded',
            null => 'unknown',
        };
    }

    /** @return array<string, mixed> */
    private function intakeStatus(EvaluationRequest $request): array
    {
        $notes = app(CreatorEvaluationRequestIntake::class)->intakeNotes($request);

        return [
            'claims_confirmed' => ($notes['claims_confirmed'] ?? false) === true,
            'audience_confirmed' => ($notes['audience_confirmed'] ?? false) === true,
            'scope_complete' => is_string($notes['scope'] ?? null) && trim($notes['scope']) !== '',
            'material_complete' => is_array($notes['material'] ?? null)
                && isset($notes['material']['type'], $notes['material']['label']),
            'ready' => $request->status === EvaluationRequestStatus::Ready,
        ];
    }

    private function nextAction(
        User $actor,
        EvaluationRequest $request,
        bool $isBilling,
        ?OrderStatus $orderStatus,
        ?PaymentStatus $paymentStatus,
        ?RefundStatus $refundStatus,
        bool $hasDeliveredReport,
    ): ?string {
        if ($isBilling) {
            return match ($request->status) {
                EvaluationRequestStatus::AwaitingPayment => 'view_payment',
                EvaluationRequestStatus::Paid,
                EvaluationRequestStatus::Intake,
                EvaluationRequestStatus::AwaitingCreator,
                EvaluationRequestStatus::Ready => $this->refundEligible(
                    $actor,
                    $request,
                    $orderStatus,
                    $paymentStatus,
                    $refundStatus,
                    $hasDeliveredReport,
                ) ? 'request_refund' : null,
                default => null,
            };
        }

        return match ($request->status) {
            EvaluationRequestStatus::Draft => 'resume_request',
            EvaluationRequestStatus::AwaitingPayment => 'continue_payment',
            EvaluationRequestStatus::Paid,
            EvaluationRequestStatus::Intake,
            EvaluationRequestStatus::AwaitingCreator => 'complete_intake',
            EvaluationRequestStatus::Ready => 'view_status',
            EvaluationRequestStatus::Cancelled,
            EvaluationRequestStatus::Refunded,
            null => null,
        };
    }

    private function refundEligible(
        User $actor,
        EvaluationRequest $request,
        ?OrderStatus $orderStatus,
        ?PaymentStatus $paymentStatus,
        ?RefundStatus $refundStatus,
        bool $hasDeliveredReport,
    ): bool {
        if (!Gate::forUser($actor)->allows('refund', $request)) {
            return false;
        }

        return in_array($request->status, [
            EvaluationRequestStatus::Paid,
            EvaluationRequestStatus::Intake,
            EvaluationRequestStatus::AwaitingCreator,
            EvaluationRequestStatus::Ready,
        ], true)
            && $orderStatus === OrderStatus::Paid
            && $paymentStatus === PaymentStatus::Paid
            && $refundStatus !== RefundStatus::Succeeded
            && $hasDeliveredReport === false;
    }

    private function roleFor(User $actor, Organization $organization): ?string
    {
        $membership = $organization->users()
            ->whereKey($actor->getKey())
            ->first();

        if ($membership === null) {
            return null;
        }

        $role = $membership->pivot?->role;

        return is_string($role) ? $role : null;
    }

    private function rawStatus(Product|ProductRelease $model): string
    {
        $status = $model->getRawOriginal('status');

        return is_string($status) ? $status : (string) $model->getAttribute('status');
    }
}
