<?php

use App\Livewire\Creator\EvaluationRequests\CreateEvaluationRequest;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('creator/organizations/{organizationId}/evaluation-requests/create/{evaluationRequestId?}', CreateEvaluationRequest::class)
        ->whereNumber('organizationId')
        ->whereNumber('evaluationRequestId')
        ->name('creator.evaluation-requests.create');
});

require __DIR__.'/settings.php';
