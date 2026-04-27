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
                'must_have_skills' => $analysis['must_have_skills'] ?? [],
                'nice_to_have_skills' => $analysis['nice_to_have_skills'] ?? [],
                'seniority' => $analysis['seniority'] ?? null,
                'role_type' => $analysis['role_type'] ?? null,
                'domain_tags' => $analysis['domain_tags'] ?? [],
                'tech_stack' => $analysis['tech_stack'] ?? [],
                'responsibilities' => $analysis['responsibilities'] ?? [],
                'company_context' => $analysis['company_context'] ?? null,
                'ai_summary' => $analysis['ai_summary'] ?? null,
                'confidence_score' => $analysis['confidence_score'] ?? null,
                'ai_provider' => $analysis['ai_provider'] ?? null,
                'ai_model' => $analysis['ai_model'] ?? null,
                'ai_generated_at' => $analysis['ai_generated_at'] ?? null,
                'ai_confidence_score' => $analysis['ai_confidence_score'] ?? null,
                'ai_raw_response' => $analysis['ai_raw_response'] ?? null,
                'analyzed_at' => now(),
            ]
        );

        $job->forceFill(['status' => 'analyzed'])->save();
    }
}
