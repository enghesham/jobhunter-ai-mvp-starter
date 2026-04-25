<?php

namespace App\Jobs;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
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

    public function handle(JobMatchScoringService $scoringService): void
    {
        $job = Job::with('analysis')->find($this->jobId);
        $profile = CandidateProfile::find($this->profileId);

        if (! $job || ! $job->analysis || ! $profile) {
            return;
        }

        $score = $scoringService->score($profile, $job);

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
                'recommendation' => $score['recommendation'],
                'matched_at' => now(),
            ]
        );

        $job->forceFill(['status' => 'matched'])->save();
    }
}
