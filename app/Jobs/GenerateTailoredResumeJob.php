<?php

namespace App\Jobs;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Resume\Domain\Models\TailoredResume;
use App\Services\AI\LlmClient;
use App\Services\Pdf\ResumePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class GenerateTailoredResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId, public int $profileId)
    {
    }

    public function handle(LlmClient $llmClient, ResumePdfService $pdfService): void
    {
        $job = Job::with('analysis')->find($this->jobId);
        $profile = CandidateProfile::with('experiences')->find($this->profileId);

        if (! $job || ! $job->analysis || ! $profile) {
            return;
        }

        $tailored = $llmClient->tailorResume([
            'full_name' => $profile->full_name,
            'headline' => $profile->headline,
            'base_summary' => $profile->base_summary,
            'core_skills' => $profile->core_skills,
        ], [
            'required_skills' => $job->analysis->required_skills,
            'role_focus' => $job->analysis->role_type,
            'summary' => $job->analysis->ai_summary,
        ]);

        $html = View::file(resource_path('views/resumes/templates/default.blade.php'), [
            'profile' => $profile,
            'job' => $job,
            'tailored' => $tailored,
        ])->render();

        $relativePath = 'resumes/tailored/job_'.$job->id.'_profile_'.$profile->id.'.html';
        $absolutePath = storage_path('app/public/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        $pdfService->generate($html, $absolutePath);

        TailoredResume::create([
            'job_id' => $job->id,
            'profile_id' => $profile->id,
            'version_name' => 'v1',
            'summary_text' => $tailored['summary'] ?? null,
            'skills_text' => implode(', ', $tailored['reordered_skills'] ?? []),
            'experience_text' => implode("\n", $tailored['highlighted_achievements'] ?? []),
            'ats_keywords' => $job->analysis->required_skills,
            'html_path' => $relativePath,
            'pdf_path' => $relativePath,
        ]);
    }
}
