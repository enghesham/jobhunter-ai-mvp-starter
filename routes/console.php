<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\AnalyzeJobJob;
use App\Jobs\MatchJobToProfileJob;
use App\Jobs\ScanJobSourceJob;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\JobSourceScanService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('jobs:scan {sourceId?} {--sync}', function (?int $sourceId = null) {
    $query = JobSource::query()->where('is_active', true);

    if ($sourceId !== null) {
        $query->whereKey($sourceId);
    }

    $sources = $query->get();

    if ($sources->isEmpty()) {
        $this->warn('No active job sources found.');

        return self::SUCCESS;
    }

    foreach ($sources as $source) {
        if ($this->option('sync')) {
            $result = app(JobSourceScanService::class)->scan($source);
            $this->info("Scanned {$source->name}: ".json_encode($result));

            continue;
        }

        ScanJobSourceJob::dispatch($source->id);
        $this->info("Queued scan for {$source->name}.");
    }

    return self::SUCCESS;
})->purpose('Scan one active job source or all active job sources');

Artisan::command('jobs:analyze {jobId}', function (int $jobId) {
    if (! Job::query()->whereKey($jobId)->exists()) {
        $this->error("Job [{$jobId}] was not found.");

        return self::FAILURE;
    }

    AnalyzeJobJob::dispatchSync($jobId);
    $this->info("Analyzed job [{$jobId}].");

    return self::SUCCESS;
})->purpose('Analyze one job description');

Artisan::command('jobs:match {jobId} {profileId=1}', function (int $jobId, int $profileId = 1) {
    if (! Job::query()->whereKey($jobId)->exists()) {
        $this->error("Job [{$jobId}] was not found.");

        return self::FAILURE;
    }

    MatchJobToProfileJob::dispatchSync($jobId, $profileId);
    $this->info("Matched job [{$jobId}] against profile [{$profileId}].");

    return self::SUCCESS;
})->purpose('Match one job against a candidate profile');
