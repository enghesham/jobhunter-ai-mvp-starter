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

    public function __construct(public int $jobId, public bool $force = false)
    {
    }

    public function handle(JobAnalysisServiceInterface $analysisService): void
    {
        $job = Job::find($this->jobId);

        if (! $job) {
            return;
        }

        $analysis = $analysisService->analyze($job, $this->force);

        if (($analysis['cache_hit'] ?? false) === true) {
            $job->forceFill(['status' => 'analyzed'])->save();

            return;
        }

        JobAnalysis::updateOrCreate(
            ['job_id' => $job->id],
            [
                'required_skills' => $analysis['required_skills'] ?? [],
                'preferred_skills' => $analysis['preferred_skills'] ?? [],
                'must_have_skills' => $analysis['must_have_skills'] ?? [],
                'nice_to_have_skills' => $analysis['nice_to_have_skills'] ?? [],
                'seniority' => $analysis['seniority'] ?? null,
                'role_type' => $analysis['role_type'] ?? null,
                'years_experience_min' => $analysis['years_experience_min'] ?? null,
                'years_experience_max' => $analysis['years_experience_max'] ?? null,
                'workplace_type' => $analysis['workplace_type'] ?? null,
                'salary_text' => $analysis['salary_text'] ?? null,
                'salary_min' => $analysis['salary_min'] ?? null,
                'salary_max' => $analysis['salary_max'] ?? null,
                'salary_currency' => $analysis['salary_currency'] ?? null,
                'location_hint' => $analysis['location_hint'] ?? null,
                'timezone_hint' => $analysis['timezone_hint'] ?? null,
                'domain_tags' => $analysis['domain_tags'] ?? [],
                'tech_stack' => $analysis['tech_stack'] ?? [],
                'skill_categories' => $analysis['skill_categories'] ?? [],
                'responsibilities' => $analysis['responsibilities'] ?? [],
                'company_context' => $analysis['company_context'] ?? null,
                'ai_summary' => $analysis['ai_summary'] ?? null,
                'confidence_score' => $analysis['confidence_score'] ?? null,
                'ai_provider' => $analysis['ai_provider'] ?? null,
                'ai_model' => $analysis['ai_model'] ?? null,
                'ai_generated_at' => $analysis['ai_generated_at'] ?? null,
                'ai_confidence_score' => $analysis['ai_confidence_score'] ?? null,
                'ai_raw_response' => $analysis['ai_raw_response'] ?? null,
                'prompt_version' => $analysis['prompt_version'] ?? null,
                'input_hash' => $analysis['input_hash'] ?? null,
                'ai_duration_ms' => $analysis['ai_duration_ms'] ?? null,
                'fallback_used' => $analysis['fallback_used'] ?? false,
                'analyzed_at' => now(),
            ]
        );

        $job->forceFill(['status' => 'analyzed'])->save();
    }
}
