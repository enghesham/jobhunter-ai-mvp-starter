<?php

use App\Modules\Answers\Http\Controllers\AnswerTemplateController;
use App\Modules\Applications\Http\Controllers\ApplicationController;
use App\Modules\Jobs\Http\Controllers\JobController;
use App\Modules\Jobs\Http\Controllers\JobSourceController;
use App\Modules\Resume\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::apiResource('job-sources', JobSourceController::class);
    Route::post('job-sources/{jobSource}/scan', [JobSourceController::class, 'scan']);

    Route::get('jobs', [JobController::class, 'index']);
    Route::get('jobs/{job}', [JobController::class, 'show']);
    Route::post('jobs/{job}/analyze', [JobController::class, 'analyze']);
    Route::post('jobs/{job}/match', [JobController::class, 'match']);
    Route::post('jobs/{job}/generate-resume', [ResumeController::class, 'generate']);

    Route::apiResource('applications', ApplicationController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('answer-templates', AnswerTemplateController::class);
});

Route::prefix('jobhunter')->name('jobhunter.')->group(function () {
    Route::apiResource('job-sources', JobSourceController::class);
    Route::post('job-sources/{jobSource}/scan', [JobSourceController::class, 'scan']);

    Route::get('jobs', [JobController::class, 'index']);
    Route::get('jobs/{job}', [JobController::class, 'show']);
    Route::post('jobs/{job}/analyze', [JobController::class, 'analyze']);
    Route::post('jobs/{job}/match', [JobController::class, 'match']);
    Route::post('jobs/{job}/generate-resume', [ResumeController::class, 'generate']);

    Route::apiResource('applications', ApplicationController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('answer-templates', AnswerTemplateController::class);
});
