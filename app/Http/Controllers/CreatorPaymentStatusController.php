<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EvaluationRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CreatorPaymentStatusController
{
    public function __invoke(Request $request): View
    {
        $requestId = $request->integer('evaluation_request');
        $evaluationRequest = EvaluationRequest::query()->findOrFail($requestId);

        if (Gate::denies('view', $evaluationRequest)) {

            throw new AuthorizationException('You are not authorized to view this evaluation request.');
        }

        return view('creator.payment-status', [
            'request' => $evaluationRequest->load('product'),
        ]);
    }
}
