<?php

namespace App\Jobs;

use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobAnalysis;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeJobJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId)
    {
    }

    public function handle(JobAnalysisServiceInterface $analysisService): void
    {
        $job = Job::find($this->jobId);

        if (! $job) {
            return;
        }

        $analysis = $analysisService->analyze($job);

        JobAnalysis::updateOrCreate(
            ['job_id' => $job->id],
            [
                'required_skills' => $analysis['required_skills'] ?? [],
                'preferred_skills' => $analysis['preferred_skills'] ?? [],
                'seniority' => $analysis['seniority'] ?? null,
                'role_type' => $analysis['role_type'] ?? null,
                'domain_tags' => $analysis['domain_tags'] ?? [],
                'ai_summary' => $analysis['ai_summary'] ?? null,
                'analyzed_at' => now(),
            ]
        );
    }
}
