<?php

namespace App\Services\Resume;

use App\Modules\Candidate\Domain\Models\CandidateExperience;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Candidate\Domain\Models\CandidateProject;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Modules\Resume\Domain\Models\TailoredResume;
use App\Services\Pdf\ResumePdfService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class ResumeGenerationService
{
    public function __construct(private readonly ResumePdfService $pdfService)
    {
    }

    public function generate(Job $job, CandidateProfile $profile, string $versionName = 'v1'): TailoredResume
    {
        $job->loadMissing('analysis');
        $profile->loadMissing(['experiences', 'projects']);

        $match = JobMatch::query()
            ->where('job_id', $job->id)
            ->where('profile_id', $profile->id)
            ->latest('matched_at')
            ->first();

        $selectedSkills = $this->selectedSkills($profile, $job);
        $experienceBullets = $this->selectedExperienceBullets($profile->experiences, $job);
        $projects = $this->selectedProjects($profile->projects, $job);
        $headline = $profile->headline ?: $job->title;
        $summary = $this->summary($profile, $job, $selectedSkills, $match);
        $atsKeywords = collect($job->analysis?->required_skills ?? [])->values()->all();

        $resumePayload = [
            'headline' => $headline,
            'professional_summary' => $summary,
            'selected_skills' => $selectedSkills,
            'selected_experience_bullets' => $experienceBullets,
            'selected_projects' => $projects,
            'ats_keywords' => $atsKeywords,
        ];

        $html = View::make('resumes.templates.default', [
            'profile' => $profile,
            'job' => $job,
            'resume' => $resumePayload,
            'match' => $match,
        ])->render();

        $basePath = "resumes/tailored/job_{$job->id}_profile_{$profile->id}_{$versionName}";
        $documentPaths = $this->pdfService->generate($html, $basePath);

        return TailoredResume::create([
            'job_id' => $job->id,
            'user_id' => $profile->user_id ?: $job->user_id,
            'profile_id' => $profile->id,
            'version_name' => $versionName,
            'headline_text' => $headline,
            'summary_text' => $summary,
            'skills_text' => implode("\n", $selectedSkills),
            'experience_text' => implode("\n", $experienceBullets),
            'projects_text' => implode("\n", $projects),
            'ats_keywords' => $atsKeywords,
            'html_path' => $documentPaths['html_path'],
            'pdf_path' => $documentPaths['pdf_path'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function selectedSkills(CandidateProfile $profile, Job $job): array
    {
        $analysisSkills = collect($job->analysis?->required_skills ?? [])
            ->map(fn (string $skill): string => mb_strtolower($skill));

        return collect($profile->core_skills ?? [])
            ->sortByDesc(fn (string $skill): int => $analysisSkills->contains(mb_strtolower($skill)) ? 1 : 0)
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, CandidateExperience> $experiences
     * @return array<int, string>
     */
    private function selectedExperienceBullets(Collection $experiences, Job $job): array
    {
        $signals = collect(array_merge(
            $job->analysis?->required_skills ?? [],
            $job->analysis?->domain_tags ?? []
        ))->map(fn (string $value): string => mb_strtolower($value))->all();

        return $experiences
            ->flatMap(function (CandidateExperience $experience) use ($signals) {
                $bullets = collect($experience->achievements ?: [])
                    ->filter(fn (mixed $achievement): bool => is_string($achievement))
                    ->values();

                if ($bullets->isEmpty() && $experience->description) {
                    $bullets = collect([$experience->description]);
                }

                return $bullets->map(function (string $bullet) use ($experience, $signals): array {
                    $score = collect($signals)->filter(fn (string $signal): bool => str_contains(mb_strtolower($bullet), $signal))->count();

                    return [
                        'text' => "{$experience->title} at {$experience->company}: ".trim($bullet),
                        'score' => $score,
                    ];
                });
            })
            ->sortByDesc('score')
            ->take(6)
            ->pluck('text')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, CandidateProject> $projects
     * @return array<int, string>
     */
    private function selectedProjects(Collection $projects, Job $job): array
    {
        $signals = collect($job->analysis?->required_skills ?? [])
            ->map(fn (string $value): string => mb_strtolower($value))
            ->all();

        return $projects
            ->map(function (CandidateProject $project) use ($signals): array {
                $text = trim(implode(' ', array_filter([
                    $project->name,
                    $project->description,
                    implode(' ', $project->tech_stack ?? []),
                ])));
                $score = collect($signals)->filter(fn (string $signal): bool => str_contains(mb_strtolower($text), $signal))->count();

                return [
                    'text' => trim($project->name.': '.$project->description),
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->take(3)
            ->pluck('text')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $selectedSkills
     */
    private function summary(CandidateProfile $profile, Job $job, array $selectedSkills, ?JobMatch $match): string
    {
        $skillsText = $selectedSkills === [] ? 'backend engineering' : implode(', ', array_slice($selectedSkills, 0, 6));
        $matchText = $match ? "Current match score: {$match->overall_score}/100." : null;

        return trim(implode(' ', array_filter([
            $profile->base_summary,
            "Tailored for {$job->title} at {$job->company_name}, emphasizing {$skillsText}.",
            $matchText,
        ])));
    }
}
