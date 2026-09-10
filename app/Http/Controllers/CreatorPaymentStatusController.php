<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EvaluationRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CreatorPaymentStatusController
{
    public function __invoke(Request $request): View
    {
        $requestId = $request->integer('evaluation_request');
        $evaluationRequest = EvaluationRequest::query()->findOrFail($requestId);

        $this->authorize('view', $evaluationRequest);

        return view('creator.payment-status', [
            'request' => $evaluationRequest->load('product'),
        ]);
    }
}
