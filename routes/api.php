<?php

use App\Modules\Answers\Http\Controllers\AnswerTemplateController;
use App\Modules\Applications\Http\Controllers\ApplicationController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Candidate\Http\Controllers\CandidateProfileController;
use App\Modules\Jobs\Http\Controllers\JobController;
use App\Modules\Jobs\Http\Controllers\JobSourceController;
use App\Modules\Matching\Http\Controllers\JobMatchController;
use App\Modules\Resume\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('guest');
    Route::post('login', [AuthController::class, 'login'])->middleware('guest');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('jobhunter')->middleware('auth:sanctum')->name('jobhunter.')->group(function () {
    Route::apiResource('job-sources', JobSourceController::class);
    Route::post('job-sources/{jobSource}/scan', [JobSourceController::class, 'scan']);
    Route::post('job-sources/{jobSource}/ingest', [JobSourceController::class, 'ingest']);

    Route::get('jobs', [JobController::class, 'index']);
    Route::get('jobs/{job}', [JobController::class, 'show']);
    Route::get('jobs/{job}/analysis', [JobController::class, 'analysis']);
    Route::post('jobs/{job}/analyze', [JobController::class, 'analyze'])->middleware('throttle:ai-heavy');
    Route::post('jobs/{job}/match', [JobController::class, 'match'])->middleware('throttle:ai-heavy');
    Route::get('matches', [JobMatchController::class, 'index']);
    Route::get('matches/{match}/explanation', [JobMatchController::class, 'explanation']);
    Route::get('resumes', [ResumeController::class, 'index']);
    Route::get('resumes/{resume}', [ResumeController::class, 'show']);
    Route::post('jobs/{job}/generate-resume', [ResumeController::class, 'generate'])->middleware('throttle:ai-heavy');

    Route::apiResource('applications', ApplicationController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('candidate-profiles/import', [CandidateProfileController::class, 'import']);
    Route::apiResource('candidate-profiles', CandidateProfileController::class);
    Route::apiResource('answer-templates', AnswerTemplateController::class);
});
