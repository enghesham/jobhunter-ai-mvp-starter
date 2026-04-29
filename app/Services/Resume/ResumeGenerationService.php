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
    public function __construct(
        private readonly ResumePdfService $pdfService,
        private readonly AiResumeTailoringService $aiResumeTailoringService,
    ) {
    }

    public function generate(Job $job, CandidateProfile $profile, string $versionName = 'v1', bool $force = false): TailoredResume
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
        $atsKeywords = collect(array_merge(
            $job->analysis?->required_skills ?? [],
            $job->analysis?->must_have_skills ?? []
        ))->unique()->values()->all();
        $warningsOrGaps = $this->warningsOrGaps($profile, $job);

        $basePayload = [
            'tailored_headline' => $headline,
            'tailored_summary' => $summary,
            'selected_skills' => $selectedSkills,
            'tailored_experience_bullets' => $experienceBullets,
            'selected_projects' => $projects,
            'ats_keywords' => $atsKeywords,
            'warnings_or_gaps' => $warningsOrGaps,
            'confidence_score' => 55,
        ];

        $aiContext = [
            'candidate_profile' => $this->candidateContext($profile),
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
            ],
            'analysis' => $job->analysis?->toArray() ?? [],
            'base_resume_payload' => $basePayload,
            'allowed_skills' => array_values(array_unique(array_merge($profile->core_skills ?? [], $profile->nice_to_have_skills ?? []))),
            'allowed_projects' => $profile->projects->pluck('name')->filter()->values()->all(),
            'allowed_keywords' => $atsKeywords,
            'source_experience_bullets' => $experienceBullets,
        ];

        $promptVersion = $this->aiResumeTailoringService->promptVersion();
        $inputHash = $this->aiResumeTailoringService->inputHash($profile, $job, $aiContext, $basePayload, $promptVersion);

        $cached = $force ? null : $this->cachedResume($job, $profile, $versionName, $promptVersion, $inputHash);

        if ($cached) {
            return $cached->loadMissing(['job', 'profile']);
        }

        $resumePayload = $this->aiResumeTailoringService->tailor($profile, $job, $aiContext, $basePayload);

        $html = View::make('resumes.templates.default', [
            'profile' => $profile,
            'job' => $job,
            'resume' => [
                'headline' => $resumePayload['tailored_headline'],
                'professional_summary' => $resumePayload['tailored_summary'],
                'selected_skills' => $resumePayload['selected_skills'],
                'selected_experience_bullets' => $resumePayload['tailored_experience_bullets'],
                'selected_projects' => $resumePayload['selected_projects'],
                'ats_keywords' => $resumePayload['ats_keywords'],
                'warnings_or_gaps' => $resumePayload['warnings_or_gaps'],
            ],
            'match' => $match,
        ])->render();

        $basePath = "resumes/tailored/job_{$job->id}_profile_{$profile->id}_{$versionName}";
        $documentPaths = $this->pdfService->generate($html, $basePath);

        $attributes = [
            'headline_text' => $resumePayload['tailored_headline'],
            'summary_text' => $resumePayload['tailored_summary'],
            'skills_text' => implode("\n", $resumePayload['selected_skills']),
            'experience_text' => implode("\n", $resumePayload['tailored_experience_bullets']),
            'projects_text' => implode("\n", $resumePayload['selected_projects']),
            'ats_keywords' => $resumePayload['ats_keywords'],
            'warnings_or_gaps' => $resumePayload['warnings_or_gaps'],
            'ai_provider' => $resumePayload['ai_provider'],
            'ai_model' => $resumePayload['ai_model'],
            'ai_generated_at' => $resumePayload['ai_generated_at'],
            'ai_confidence_score' => $resumePayload['ai_confidence_score'] ?? $resumePayload['confidence_score'] ?? null,
            'ai_raw_response' => $resumePayload['ai_raw_response'],
            'prompt_version' => $resumePayload['prompt_version'],
            'input_hash' => $resumePayload['input_hash'],
            'ai_duration_ms' => $resumePayload['ai_duration_ms'],
            'fallback_used' => $resumePayload['fallback_used'],
            'html_path' => $documentPaths['html_path'],
            'pdf_path' => $documentPaths['pdf_path'],
        ];

        return TailoredResume::updateOrCreate(
            [
                'job_id' => $job->id,
                'profile_id' => $profile->id,
                'version_name' => $versionName,
            ],
            array_merge($attributes, [
                'user_id' => $profile->user_id ?: $job->user_id,
            ])
        );
    }

    private function cachedResume(Job $job, CandidateProfile $profile, string $versionName, string $promptVersion, string $inputHash): ?TailoredResume
    {
        if (! config('jobhunter.ai_cache_enabled', true)) {
            return null;
        }

        return TailoredResume::query()
            ->where('job_id', $job->id)
            ->where('profile_id', $profile->id)
            ->where('version_name', $versionName)
            ->where('prompt_version', $promptVersion)
            ->where('input_hash', $inputHash)
            ->latest('id')
            ->first();
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

    /**
     * @return array<int, string>
     */
    private function warningsOrGaps(CandidateProfile $profile, Job $job): array
    {
        $candidateSkills = collect(array_merge($profile->core_skills ?? [], $profile->nice_to_have_skills ?? []))
            ->map(fn (string $skill): string => mb_strtolower($skill))
            ->all();

        return collect($job->analysis?->must_have_skills ?? [])
            ->filter(fn (string $skill): bool => ! in_array(mb_strtolower($skill), $candidateSkills, true))
            ->map(fn (string $skill): string => "Requirement not evidenced in profile: {$skill}.")
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateContext(CandidateProfile $profile): array
    {
        return [
            'full_name' => $profile->full_name,
            'headline' => $profile->headline,
            'base_summary' => $profile->base_summary,
            'years_experience' => $profile->years_experience,
            'preferred_roles' => $profile->preferred_roles,
            'preferred_locations' => $profile->preferred_locations,
            'core_skills' => $profile->core_skills,
            'nice_to_have_skills' => $profile->nice_to_have_skills,
            'experiences' => $profile->experiences->map(fn (CandidateExperience $experience) => [
                'company' => $experience->company,
                'title' => $experience->title,
                'description' => $experience->description,
                'achievements' => $experience->achievements ?? [],
                'tech_stack' => $experience->tech_stack ?? [],
            ])->values()->all(),
            'projects' => $profile->projects->map(fn (CandidateProject $project) => [
                'name' => $project->name,
                'description' => $project->description,
                'tech_stack' => $project->tech_stack ?? [],
            ])->values()->all(),
        ];
    }
}
