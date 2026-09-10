<?php

use App\Http\Controllers\CreatorPaymentStatusController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Creator\EvaluationRequests\CreateEvaluationRequest;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware(['web']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('creator/organizations/{organizationId}/evaluation-requests/create/{evaluationRequestId?}', CreateEvaluationRequest::class)
        ->whereNumber('organizationId')
        ->whereNumber('evaluationRequestId')
        ->name('creator.evaluation-requests.create');

    Route::get('creator/payment/success', CreatorPaymentStatusController::class)->name('creator.payment.success');
    Route::get('creator/payment/cancelled', CreatorPaymentStatusController::class)->name('creator.payment.cancelled');
});

require __DIR__.'/settings.php';
