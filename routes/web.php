<?php

use App\Http\Controllers\CreatorPaymentStatusController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Creator\EvaluationRequests\CreateEvaluationRequest;
use App\Livewire\Creator\Reports\ShowReport;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.pages.home')->name('home');

Route::view('how-it-works', 'public.pages.placeholder', [
    'title' => 'How validation works',
    'description' => 'Understand the independent validation process used by Valid.guide.',
    'message' => 'The validation process and methodology are being prepared for publication.',
])->name('public.how-it-works');

Route::view('for-creators', 'public.pages.placeholder', [
    'title' => 'For creators',
    'description' => 'Learn how creators can submit an online learning product for independent validation.',
    'message' => 'The creator information and evaluation entry experience are being prepared.',
])->name('public.creators');

Route::view('for-buyers', 'public.pages.placeholder', [
    'title' => 'For buyers and learners',
    'description' => 'Use Valid.guide validation information to make better-informed learning decisions.',
    'message' => 'The buyer and learner information experience is being prepared.',
])->name('public.buyers');

Route::get('verify', [PublicVerificationController::class, 'index'])->name('public.verify');
Route::get('verify/{verificationIdentifier}', [PublicVerificationController::class, 'show'])
    ->where('verificationIdentifier', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->name('public.verify.show');

Route::view('pricing', 'public.pages.placeholder', [
    'title' => 'Pricing',
    'description' => 'Learn about Valid.guide evaluation packages and pricing.',
    'message' => 'Pricing information is being prepared for publication.',
])->name('public.pricing');

Route::view('faq', 'public.pages.placeholder', [
    'title' => 'Frequently asked questions',
    'description' => 'Answers to common questions about Valid.guide validation.',
    'message' => 'Frequently asked questions are being prepared for publication.',
])->name('public.faq');

Route::view('about', 'public.pages.placeholder', [
    'title' => 'About Valid.guide',
    'description' => 'Learn about Valid.guide, its independence principles, and its validation model.',
    'message' => 'Information about Valid.guide and its independence principles is being prepared for publication.',
])->name('public.about');

Route::post('webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware(['web']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('creator/organizations/{organizationId}/evaluation-requests/create/{evaluationRequestId?}', CreateEvaluationRequest::class)
        ->whereNumber('organizationId')
        ->whereNumber('evaluationRequestId')
        ->name('creator.evaluation-requests.create');

    Route::get('creator/evaluations/{evaluationId}/report', ShowReport::class)
        ->whereNumber('evaluationId')
        ->name('creator.reports.show');

    Route::get('creator/payment/success', CreatorPaymentStatusController::class)->name('creator.payment.success');
    Route::get('creator/payment/cancelled', CreatorPaymentStatusController::class)->name('creator.payment.cancelled');
});

require __DIR__.'/settings.php';
