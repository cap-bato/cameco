<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\System\Onboarding\SystemOnboardingController;
use App\Http\Controllers\HR\ATS\JobPostingController;
use App\Http\Controllers\HR\ATS\CandidateController;
use App\Http\Controllers\HR\ATS\ApplicationController;
use App\Http\Controllers\HR\ATS\InterviewController;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// HR ATS MODULE
Route::middleware(['auth'])
    ->prefix('hr/ats')
    ->name('hr.ats.')
    ->group(function () {

        // System onboarding
        Route::post('/system/onboarding/start', [SystemOnboardingController::class, 'start']);
        Route::post('/system/onboarding/skip', [SystemOnboardingController::class, 'skip']);
        Route::post('/system/onboarding/complete', [SystemOnboardingController::class, 'complete'])
            ->name('system.onboarding.complete');
        Route::post('/system/onboarding/initialize-company', [SystemOnboardingController::class, 'initializeCompany'])
            ->name('system.onboarding.initialize-company');

        // Job postings
        Route::resource('job-postings', JobPostingController::class);
        Route::post('job-postings/{jobPosting}/publish', [JobPostingController::class, 'publish'])
            ->name('job-postings.publish');
        Route::post('job-postings/{jobPosting}/close', [JobPostingController::class, 'close'])
            ->name('job-postings.close');

        // Candidates
        Route::resource('candidates', CandidateController::class);
        Route::post('candidates/{candidate}/add-note', [CandidateController::class, 'addNote'])
            ->name('candidates.add-note');

        // Applications
        Route::resource('applications', ApplicationController::class);
        Route::post('applications/{application}/update-status', [ApplicationController::class, 'updateStatus'])
            ->name('applications.update-status');
        Route::post('applications/{application}/shortlist', [ApplicationController::class, 'shortlist'])
            ->name('applications.shortlist');
        Route::post('applications/{application}/reject', [ApplicationController::class, 'reject'])
            ->name('applications.reject');
        Route::post('applications/{application}/schedule-interview', [ApplicationController::class, 'scheduleInterview'])
            ->name('applications.schedule-interview');
        Route::post('applications/{application}/generate-offer', [ApplicationController::class, 'generateOffer'])
            ->name('applications.generate-offer');

        // Interviews
        Route::post('interviews', [InterviewController::class, 'store'])->name('interviews.store');
        Route::put('interviews/{interview}', [InterviewController::class, 'update'])->name('interviews.update');
        Route::post('interviews/{interview}/cancel', [InterviewController::class, 'cancel'])->name('interviews.cancel');
        Route::patch('interviews/{interview}/feedback', [InterviewController::class, 'updateFeedback'])->name('interviews.feedback');

        // hiring pipeline 
        Route::put('pipeline/applications/{application}/move', [ApplicationController::class, 'move'])
    ->name('applications.move');
    
});
