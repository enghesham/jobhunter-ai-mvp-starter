<?php

namespace App\Jobs;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Services\Matching\JobMatchExplanationService;
use App\Services\Matching\JobMatchScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchJobToProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $jobId,
        public int $profileId,
        public bool $force = false,
        public ?int $jobPathId = null,
    )
    {
    }

    public function handle(JobMatchScoringService $scoringService, JobMatchExplanationService $explanationService): void
    {
        $job = Job::with('analysis')->find($this->jobId);
        $profile = CandidateProfile::with(['experiences', 'projects'])->find($this->profileId);
        $jobPath = $this->jobPathId
            ? JobPath::query()->where('user_id', $profile?->user_id)->whereKey($this->jobPathId)->first()
            : null;

        if (! $job || ! $job->analysis || ! $profile) {
            return;
        }

        $contextKey = $jobPath ? "path:{$jobPath->id}" : 'primary';
        $score = $scoringService->score($profile, $job, $jobPath);
        $explanation = $explanationService->explain($profile, $job, $score, $this->force, $contextKey);

        if (($explanation['cache_hit'] ?? false) === true) {
            $job->forceFill(['status' => 'matched'])->save();

            return;
        }

        JobMatch::updateOrCreate(
            ['job_id' => $job->id, 'profile_id' => $profile->id, 'context_key' => $contextKey],
            [
                'user_id' => $profile->user_id ?: $job->user_id,
                'job_path_id' => $jobPath?->id,
                'overall_score' => $score['overall_score'],
                'title_score' => $score['title_score'],
                'skill_score' => $score['skill_score'],
                'experience_score' => $score['experience_score'],
                'seniority_score' => $score['seniority_score'],
                'location_score' => $score['location_score'],
                'backend_focus_score' => $score['backend_focus_score'],
                'domain_score' => $score['domain_score'],
                'path_relevance_score' => $score['path_relevance_score'] ?? null,
                'path_relevance_reasons' => $score['path_relevance_reasons'] ?? [],
                'notes' => $score['notes'],
                'why_matched' => $explanation['why_matched'] ?? null,
                'missing_skills' => $explanation['missing_skills'] ?? [],
                'missing_required_skills' => $score['missing_required_skills'] ?? ($explanation['missing_skills'] ?? []),
                'nice_to_have_gaps' => $score['nice_to_have_gaps'] ?? [],
                'strength_areas' => $explanation['strength_areas'] ?? [],
                'risk_flags' => $explanation['risk_flags'] ?? [],
                'resume_focus_points' => $explanation['resume_focus_points'] ?? [],
                'ai_recommendation_summary' => $explanation['ai_recommendation_summary'] ?? null,
                'recommendation' => $score['recommendation'],
                'recommendation_action' => $score['recommendation_action'],
                'ai_provider' => $explanation['ai_provider'] ?? null,
                'ai_model' => $explanation['ai_model'] ?? null,
                'ai_generated_at' => $explanation['ai_generated_at'] ?? null,
                'ai_confidence_score' => $explanation['ai_confidence_score'] ?? null,
                'ai_raw_response' => $explanation['ai_raw_response'] ?? null,
                'prompt_version' => $explanation['prompt_version'] ?? null,
                'input_hash' => $explanation['input_hash'] ?? null,
                'ai_duration_ms' => $explanation['ai_duration_ms'] ?? null,
                'fallback_used' => $explanation['fallback_used'] ?? false,
                'matched_at' => now(),
            ]
        );

        $job->forceFill(['status' => 'matched'])->save();
    }
}
