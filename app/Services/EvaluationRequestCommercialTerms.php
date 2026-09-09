<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Models\ServicePackage;
use Illuminate\Support\Facades\DB;

final class EvaluationRequestCommercialTerms
{
    public function applyPackage(
        EvaluationRequest $request,
        ServicePackage $package,
        string $complexity,
    ): EvaluationRequest {
        if (! in_array($request->status, [
            EvaluationRequestStatus::Draft,
            EvaluationRequestStatus::AwaitingPayment,
        ], true)) {
            throw new DomainStateTransitionException('Commercial terms cannot be changed after payment has started.');
        }

        if ($request->status === EvaluationRequestStatus::AwaitingPayment) {
            throw new DomainStateTransitionException('Commercial terms are frozen while awaiting payment.');
        }

        if ($package->status !== 'active') {
            throw new DomainStateTransitionException('Only active service packages can be purchased.');
        }

        $allowedComplexities = $package->complexity_levels ?? [];
        if ($allowedComplexities !== [] && ! in_array($complexity, $allowedComplexities, true)) {
            throw new DomainStateTransitionException('The selected complexity is not available for this service package.');
        }

        return DB::transaction(function () use ($request, $package, $complexity): EvaluationRequest {
            $request = EvaluationRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($request->status !== EvaluationRequestStatus::Draft) {
                throw new DomainStateTransitionException('Commercial terms can only be selected on a draft request.');
            }

            $request->service_package_id = $package->id;
            $request->service_package_name_snapshot = $package->name;
            $request->service_package_description_snapshot = $package->description;
            $request->service_package_terms_snapshot = [
                'product_types' => $package->product_types,
                'complexity_levels' => $package->complexity_levels,
                'price' => $package->price,
                'currency' => $package->currency,
            ];
            $request->service_package = $package->slug;
            $request->complexity = $complexity;
            $request->quoted_price = $package->price;
            $request->currency = $package->currency;
            $request->save();

            AuditLogger::record(
                event: 'evaluation_request.commercial_terms_applied',
                auditable: $request,
                after: [
                    'service_package_id' => $package->id,
                    'service_package_name' => $package->name,
                    'complexity' => $complexity,
                    'quoted_price' => $package->price,
                    'currency' => $package->currency,
                ],
            );

            return $request->refresh();
        });
    }
}
