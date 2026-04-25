<?php

namespace App\Jobs;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\Resume\ResumeGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTailoredResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId, public int $profileId)
    {
    }

    public function handle(ResumeGenerationService $resumeGenerationService): void
    {
        $job = Job::with(['analysis', 'matches'])->find($this->jobId);
        $profile = CandidateProfile::with(['experiences', 'projects'])->find($this->profileId);

        if (! $job || ! $job->analysis || ! $profile) {
            return;
        }

        $resumeGenerationService->generate($job, $profile);
    }
}
