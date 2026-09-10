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
    public function __construct(
        private readonly EvaluationQuoteService $quoteService,
    ) {}

    public function applyPackage(
        EvaluationRequest $request,
        ServicePackage $package,
        string $complexity,
    ): EvaluationRequest {
        return DB::transaction(function () use ($request, $package, $complexity): EvaluationRequest {
            $request = EvaluationRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            /** @var EvaluationRequest $request */
            if ($request->status !== EvaluationRequestStatus::Draft) {
                throw new DomainStateTransitionException('Commercial terms can only be selected on a draft request.');
            }

            $complexityEnum = EvaluationComplexity::tryFrom($complexity);
            if ($complexityEnum === null) {
                throw new DomainStateTransitionException('The selected complexity is invalid.');
            }

            $product = $request->product()->firstOrFail();
            $quote = $this->quoteService->quote($package, $product->product_type, $complexityEnum);

            $request->service_package_id = $quote->servicePackageId;
            $request->service_package_name_snapshot = $quote->servicePackageName;
            $request->service_package_description_snapshot = $quote->servicePackageDescription;
            $request->service_package_terms_snapshot = [
                'product_types' => $quote->productTypes,
                'complexity_levels' => $quote->complexityLevels,
                'price_minor' => $quote->amountMinor,
                'currency' => $quote->currency,
            ];
            $request->service_package = $package->slug;
            $request->complexity = $quote->complexity;
            $request->quoted_amount_minor = $quote->amountMinor;
            $request->quoted_price = number_format($quote->amountMinor / 100, 2, '.', '');
            $request->currency = $quote->currency;
            $request->save();

            AuditLogger::record(
                event: 'evaluation_request.commercial_terms_applied',
                auditable: $request,
                after: [
                    'service_package_id' => $quote->servicePackageId,
                    'service_package_name' => $quote->servicePackageName,
                    'complexity' => $complexity,
                    'quoted_amount_minor' => $quote->amountMinor,
                    'currency' => $quote->currency,
                ],
            );

            return $request->refresh();
        });
    }
}
