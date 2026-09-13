<?php

use App\Http\Controllers\CommunityReportController;
use App\Http\Controllers\CreatorMarketplaceController;
use App\Http\Controllers\CreatorPaymentStatusController;
use App\Http\Controllers\ExpertMarketplaceController;
use App\Http\Controllers\PublicCommunityController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Controllers\PublicExpertController;
use App\Http\Controllers\PublicMarketplaceController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Creator\EvaluationRequests\CreateEvaluationRequest;
use App\Livewire\Creator\Reports\ShowGuidance;
use App\Livewire\Creator\Reports\ShowReport;
use App\Livewire\Expert\Community\Contributions;
use App\Livewire\Expert\Opportunities;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.pages.home')->name('home');

Route::view('how-it-works', 'public.pages.how-it-works')->name('public.how-it-works');
Route::view('for-creators', 'public.pages.creators')->name('public.creators');
Route::view('for-buyers', 'public.pages.buyers')->name('public.buyers');

Route::get('directory', [PublicDirectoryController::class, 'index'])->name('public.directory');
Route::get('products/{slug}', [PublicProductController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->name('public.products.show');
Route::get('experts', [PublicExpertController::class, 'index'])->name('public.experts');
Route::get('experts/{slug}', [PublicExpertController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->name('public.experts.show');
Route::get('marketplace', [PublicMarketplaceController::class, 'index'])->name('public.marketplace');
Route::get('marketplace/{slug}', [PublicMarketplaceController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->name('public.marketplace.show');
Route::get('community', [PublicCommunityController::class, 'index'])->name('public.community');
Route::get('community/{slug}', [PublicCommunityController::class, 'show'])
    ->where('slug', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->name('public.community.show');
Route::get('verify', [PublicVerificationController::class, 'index'])->name('public.verify');
Route::get('verify/{verificationIdentifier}', [PublicVerificationController::class, 'show'])
    ->where('verificationIdentifier', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->name('public.verify.show');

Route::view('pricing', 'public.pages.pricing')->name('public.pricing');
Route::view('faq', 'public.pages.faq')->name('public.faq');
Route::view('about', 'public.pages.about')->name('public.about');

Route::post('webhooks/stripe', StripeWebhookController::class)->withoutMiddleware(['web']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('expert/opportunities', Opportunities::class)->name('expert.opportunities');
    Route::get('expert/community', Contributions::class)->name('expert.community');
    Route::get('expert/marketplace', [ExpertMarketplaceController::class, 'index'])->name('expert.marketplace');
    Route::post('expert/marketplace', [ExpertMarketplaceController::class, 'store'])->name('expert.marketplace.store');
    Route::post('expert/marketplace/{service}/publish', [ExpertMarketplaceController::class, 'publish'])->name('expert.marketplace.publish');
    Route::post('expert/marketplace/{service}/pause', [ExpertMarketplaceController::class, 'pause'])->name('expert.marketplace.pause');
    Route::post('expert/marketplace/{service}/archive', [ExpertMarketplaceController::class, 'archive'])->name('expert.marketplace.archive');
    Route::post('expert/marketplace/transactions/{transaction}/start', [ExpertMarketplaceController::class, 'startTransaction'])->name('expert.marketplace.transactions.start');
    Route::post('expert/marketplace/transactions/{transaction}/complete', [ExpertMarketplaceController::class, 'completeTransaction'])->name('expert.marketplace.transactions.complete');
    Route::get('creator/marketplace', [CreatorMarketplaceController::class, 'index'])->name('creator.marketplace');
    Route::post('creator/marketplace/{service}/purchase', [CreatorMarketplaceController::class, 'purchase'])->name('creator.marketplace.purchase');
    Route::post('creator/marketplace/transactions/{transaction}/cancel', [CreatorMarketplaceController::class, 'cancel'])->name('creator.marketplace.transactions.cancel');
    Route::post('community/{contribution}/reports', [CommunityReportController::class, 'store'])->name('community.reports.store');
    Route::get('creator/organizations/{organizationId}/evaluation-requests/create/{evaluationRequestId?}', CreateEvaluationRequest::class)
        ->whereNumber('organizationId')->whereNumber('evaluationRequestId')->name('creator.evaluation-requests.create');
    Route::get('creator/evaluations/{evaluationId}/report', ShowReport::class)
        ->whereNumber('evaluationId')->name('creator.reports.show');
    Route::get('creator/evaluations/{evaluationId}/guidance', ShowGuidance::class)
        ->whereNumber('evaluationId')->name('creator.reports.guidance');
    Route::get('creator/payment/success', CreatorPaymentStatusController::class)->name('creator.payment.success');
    Route::get('creator/payment/cancelled', CreatorPaymentStatusController::class)->name('creator.payment.cancelled');
});

require __DIR__.'/settings.php';
