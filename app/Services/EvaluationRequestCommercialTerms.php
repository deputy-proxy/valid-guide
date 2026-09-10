<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationComplexity;
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
        return DB::transaction(function () use ($request, $package, $complexity): EvaluationRequest {
            $request = EvaluationRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            /** @var EvaluationRequest $request */
            if ($request->status !== EvaluationRequestStatus::Draft) {
                throw new DomainStateTransitionException('Commercial terms can only be selected on a draft request.');
            }

            if ($package->status !== 'active') {
                throw new DomainStateTransitionException('Only active service packages can be purchased.');
            }

            $allowedComplexities = $package->complexity_levels ?? [];
            if ($allowedComplexities !== [] && ! in_array($complexity, $allowedComplexities, true)) {
                throw new DomainStateTransitionException('The selected complexity is not available for this service package.');
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
            $request->complexity = EvaluationComplexity::from($complexity);
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
