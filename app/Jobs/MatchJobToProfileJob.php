<?php

namespace App\Jobs;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
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

    public function __construct(public int $jobId, public int $profileId)
    {
    }

    public function handle(JobMatchScoringService $scoringService, JobMatchExplanationService $explanationService): void
    {
        $job = Job::with('analysis')->find($this->jobId);
        $profile = CandidateProfile::find($this->profileId);

        if (! $job || ! $job->analysis || ! $profile) {
            return;
        }

        $score = $scoringService->score($profile, $job);
        $explanation = $explanationService->explain($profile, $job, $score);

        JobMatch::updateOrCreate(
            ['job_id' => $job->id, 'profile_id' => $profile->id],
            [
                'user_id' => $profile->user_id ?: $job->user_id,
                'overall_score' => $score['overall_score'],
                'title_score' => $score['title_score'],
                'skill_score' => $score['skill_score'],
                'seniority_score' => $score['seniority_score'],
                'location_score' => $score['location_score'],
                'backend_focus_score' => $score['backend_focus_score'],
                'domain_score' => $score['domain_score'],
                'notes' => $score['notes'],
                'why_matched' => $explanation['why_matched'] ?? null,
                'missing_skills' => $explanation['missing_skills'] ?? [],
                'strength_areas' => $explanation['strength_areas'] ?? [],
                'risk_flags' => $explanation['risk_flags'] ?? [],
                'resume_focus_points' => $explanation['resume_focus_points'] ?? [],
                'ai_recommendation_summary' => $explanation['ai_recommendation_summary'] ?? null,
                'recommendation' => $score['recommendation'],
                'ai_provider' => $explanation['ai_provider'] ?? null,
                'ai_model' => $explanation['ai_model'] ?? null,
                'ai_generated_at' => $explanation['ai_generated_at'] ?? null,
                'ai_confidence_score' => $explanation['ai_confidence_score'] ?? null,
                'ai_raw_response' => $explanation['ai_raw_response'] ?? null,
                'matched_at' => now(),
            ]
        );

        $job->forceFill(['status' => 'matched'])->save();
    }
}
