<?php

namespace App\Services\Matching;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;

class JobMatchScoringService
{
    /**
     * @return array<string, mixed>
     */
    public function score(CandidateProfile $profile, Job $job, ?JobPath $jobPath = null): array
    {
        $analysis = $job->analysis;
        $candidateSkills = $this->normalize($profile->core_skills ?? []);
        $requiredSkills = $this->normalize($analysis?->required_skills ?? []);
        $preferredSkills = $this->normalize($analysis?->preferred_skills ?? []);
        $matchedSkills = array_values(array_intersect($candidateSkills, $requiredSkills));
        $missingRequiredSkills = $this->originalValues($analysis?->required_skills ?? [], array_diff($requiredSkills, $matchedSkills));
        $niceToHaveGaps = $this->originalValues($analysis?->preferred_skills ?? [], array_diff($preferredSkills, $candidateSkills));

        $skillScore = $this->percentage(count($matchedSkills), max(count($requiredSkills), 1));
        $experienceScore = $this->experienceScore($profile, $job, $requiredSkills, $preferredSkills);
        $titleScore = $this->titleScore($profile, $job);
        $seniorityScore = $this->seniorityScore($profile, $analysis?->seniority);
        $locationScore = $this->locationScore($profile, $job);
        $backendFocusScore = $analysis?->role_type === 'backend' ? 95 : ($analysis?->role_type === 'full_stack' ? 82 : 60);
        $domainScore = $this->domainScore($profile, $analysis?->domain_tags ?? []);

        $baseOverall = (int) round(
            ($skillScore * 0.30)
            + ($experienceScore * 0.20)
            + ($titleScore * 0.20)
            + ($seniorityScore * 0.15)
            + ($locationScore * 0.10)
            + ($backendFocusScore * 0.03)
            + ($domainScore * 0.02)
        );
        $pathScore = $jobPath ? $this->pathRelevanceScore($jobPath, $job) : null;
        $overall = $pathScore
            ? (int) round(($baseOverall * 0.85) + ($pathScore['score'] * 0.15))
            : $baseOverall;

        $strengthAreas = $this->buildStrengthAreas($matchedSkills, $profile, $job, $domainScore, $experienceScore);
        $recommendation = $this->recommendation($overall);
        $recommendationAction = $this->recommendationAction($overall, $missingRequiredSkills, $experienceScore);

        return [
            'overall_score' => $overall,
            'title_score' => $titleScore,
            'skill_score' => $skillScore,
            'skills_score' => $skillScore,
            'experience_score' => $experienceScore,
            'seniority_score' => $seniorityScore,
            'location_score' => $locationScore,
            'backend_focus_score' => $backendFocusScore,
            'domain_score' => $domainScore,
            'path_relevance_score' => $pathScore['score'] ?? null,
            'path_relevance_reasons' => $pathScore['reasons'] ?? [],
            'missing_required_skills' => $missingRequiredSkills,
            'nice_to_have_gaps' => $niceToHaveGaps,
            'strength_areas' => $strengthAreas,
            'recommendation' => $recommendation,
            'recommendation_action' => $recommendationAction,
            'notes' => $this->notes($matchedSkills, $missingRequiredSkills, $niceToHaveGaps, $analysis?->role_type, $analysis?->seniority, $recommendationAction),
        ];
    }

    /**
     * @return array{score: int, reasons: array<int, string>}
     */
    private function pathRelevanceScore(JobPath $jobPath, Job $job): array
    {
        $text = mb_strtolower(implode(' ', array_filter([
            $job->title,
            $job->company_name,
            $job->location,
            $job->remote_type,
            $job->employment_type,
            $job->description_clean,
            $job->description_raw,
            $job->analysis?->ai_summary,
            implode(' ', $job->analysis?->required_skills ?? []),
            implode(' ', $job->analysis?->preferred_skills ?? []),
            implode(' ', $job->analysis?->tech_stack ?? []),
        ])));
        $score = 0;
        $reasons = [];

        $roleHits = $this->matchedNeedles($text, $jobPath->target_roles ?? []);
        if ($roleHits !== []) {
            $score += min(30, count($roleHits) * 15);
            $reasons[] = 'Target role alignment: '.implode(', ', array_slice($roleHits, 0, 3));
        }

        $requiredHits = $this->matchedNeedles($text, $jobPath->required_skills ?? []);
        if ($requiredHits !== []) {
            $score += min(30, count($requiredHits) * 8);
            $reasons[] = 'Required path skills found: '.implode(', ', array_slice($requiredHits, 0, 4));
        }

        $keywordHits = $this->matchedNeedles($text, $jobPath->include_keywords ?? []);
        if ($keywordHits !== []) {
            $score += min(20, count($keywordHits) * 5);
            $reasons[] = 'Path keywords found: '.implode(', ', array_slice($keywordHits, 0, 4));
        }

        $domainHits = $this->matchedNeedles($text, $jobPath->target_domains ?? []);
        if ($domainHits !== []) {
            $score += min(10, count($domainHits) * 5);
            $reasons[] = 'Domain alignment: '.implode(', ', array_slice($domainHits, 0, 2));
        }

        if ($this->remoteMatchesPath($jobPath, $job)) {
            $score += 10;
            $reasons[] = 'Workplace preference matches.';
        }

        return [
            'score' => max(0, min(100, $score)),
            'reasons' => $reasons,
        ];
    }

    /**
     * @param array<int, string> $needles
     * @return array<int, string>
     */
    private function matchedNeedles(string $text, array $needles): array
    {
        return collect($needles)
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '' && str_contains($text, mb_strtolower($value)))
            ->unique()
            ->values()
            ->all();
    }

    private function remoteMatchesPath(JobPath $jobPath, Job $job): bool
    {
        return match ($jobPath->remote_preference) {
            'remote' => (bool) $job->is_remote || $job->remote_type === 'remote',
            'hybrid' => in_array($job->remote_type, ['hybrid', 'remote'], true),
            'onsite' => $job->remote_type === 'onsite' || (! $job->is_remote && $job->remote_type === null),
            default => true,
        };
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function normalize(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): string => mb_strtolower(trim($value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function titleScore(CandidateProfile $profile, Job $job): int
    {
        $title = mb_strtolower($job->title);
        $preferredRoles = $this->normalize($profile->preferred_roles ?? []);

        if (collect($preferredRoles)->contains(fn (string $role): bool => str_contains($title, $role) || str_contains($role, $title))) {
            return 95;
        }

        if (str_contains($title, 'backend') || str_contains($title, 'laravel') || str_contains($title, 'php') || str_contains($title, 'python')) {
            return 90;
        }

        if (str_contains($title, 'full stack') || str_contains($title, 'fullstack')) {
            return 78;
        }

        return 58;
    }

    private function seniorityScore(CandidateProfile $profile, ?string $seniority): int
    {
        if (in_array($seniority, ['senior', 'lead', 'staff'], true)) {
            return $profile->years_experience >= 8 ? 94 : 72;
        }

        if ($seniority === 'junior') {
            return 45;
        }

        return 80;
    }

    private function locationScore(CandidateProfile $profile, Job $job): int
    {
        if ($job->remote_type === 'remote') {
            return 95;
        }

        $location = mb_strtolower($job->location ?: '');
        $preferred = $this->normalize($profile->preferred_locations ?? []);

        if ($location !== '' && collect($preferred)->contains(fn (string $value): bool => str_contains($location, $value))) {
            return 88;
        }

        return 70;
    }

    /**
     * @param array<int, string> $domainTags
     */
    private function domainScore(CandidateProfile $profile, array $domainTags): int
    {
        $profileEvidence = mb_strtolower(implode(' ', array_filter([
            $profile->headline,
            $profile->base_summary,
            implode(' ', $profile->core_skills ?? []),
            implode(' ', $profile->nice_to_have_skills ?? []),
        ])));

        $matched = collect($domainTags)
            ->filter(fn (string $tag): bool => $tag !== '' && str_contains($profileEvidence, mb_strtolower($tag)))
            ->count();

        if ($matched > 0) {
            return min(95, 70 + ($matched * 10));
        }

        if (array_intersect($domainTags, ['saas', 'search', 'ai', 'cloud'])) {
            return 82;
        }

        return 75;
    }

    /**
     * @param array<int, string> $requiredSkills
     * @param array<int, string> $preferredSkills
     */
    private function experienceScore(CandidateProfile $profile, Job $job, array $requiredSkills, array $preferredSkills): int
    {
        $analysis = $job->analysis;
        $experiences = $profile->relationLoaded('experiences') ? $profile->experiences : collect();
        $projects = $profile->relationLoaded('projects') ? $profile->projects : collect();
        $minYears = $analysis?->years_experience_min;
        $yearsAlignment = 80;

        if (is_int($minYears) || is_float($minYears)) {
            $yearsAlignment = $profile->years_experience >= $minYears
                ? 95
                : max(35, 95 - (($minYears - $profile->years_experience) * 12));
        }

        $experienceEvidenceText = mb_strtolower(implode(' ', array_filter([
            $profile->headline,
            $profile->base_summary,
            implode(' ', $profile->core_skills ?? []),
            implode(' ', $profile->nice_to_have_skills ?? []),
            $experiences->map(fn ($experience) => implode(' ', array_filter([
                $experience->title,
                $experience->company,
                $experience->description,
                implode(' ', $experience->tech_stack ?? []),
                implode(' ', $experience->achievements ?? []),
            ])))->implode(' '),
            $projects->map(fn ($project) => implode(' ', array_filter([
                $project->name,
                $project->description,
                implode(' ', $project->tech_stack ?? []),
            ])))->implode(' '),
        ])));

        $evidenceSkills = array_values(array_unique(array_merge($requiredSkills, $preferredSkills)));
        $matchedEvidence = collect($evidenceSkills)
            ->filter(fn (string $skill): bool => $skill !== '' && str_contains($experienceEvidenceText, $skill))
            ->count();

        $evidenceScore = $this->percentage($matchedEvidence, max(count($evidenceSkills), 1));

        return (int) round(($yearsAlignment * 0.6) + ($evidenceScore * 0.4));
    }

    /**
     * @param array<int, string> $originalValues
     * @param array<int, string> $normalizedSubset
     * @return array<int, string>
     */
    private function originalValues(array $originalValues, array $normalizedSubset): array
    {
        $lookup = array_flip($normalizedSubset);

        return collect($originalValues)
            ->filter(fn (string $value): bool => isset($lookup[mb_strtolower(trim($value))]))
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $matchedSkills
     * @return array<int, string>
     */
    private function buildStrengthAreas(array $matchedSkills, CandidateProfile $profile, Job $job, int $domainScore, int $experienceScore): array
    {
        $areas = [];

        foreach (array_slice($matchedSkills, 0, 4) as $skill) {
            $areas[] = $skill;
        }

        if ($experienceScore >= 85) {
            $areas[] = 'Relevant hands-on experience';
        }

        if ($domainScore >= 85) {
            $areas[] = 'Domain alignment';
        }

        if (str_contains(mb_strtolower($job->title), 'backend')) {
            $areas[] = 'Backend-focused title match';
        }

        return array_values(array_unique($areas));
    }

    /**
     * @param array<int, string> $matchedSkills
     * @param array<int, string> $missingRequiredSkills
     * @param array<int, string> $niceToHaveGaps
     */
    private function notes(
        array $matchedSkills,
        array $missingRequiredSkills,
        array $niceToHaveGaps,
        ?string $roleType,
        ?string $seniority,
        string $recommendationAction,
    ): string {
        return sprintf(
            'Matched skills: %s. Missing required: %s. Nice-to-have gaps: %s. Role type: %s. Seniority: %s. Recommendation: %s.',
            $matchedSkills === [] ? 'none detected' : implode(', ', $matchedSkills),
            $missingRequiredSkills === [] ? 'none' : implode(', ', $missingRequiredSkills),
            $niceToHaveGaps === [] ? 'none' : implode(', ', array_slice($niceToHaveGaps, 0, 4)),
            $roleType ?: 'unknown',
            $seniority ?: 'unknown',
            $recommendationAction,
        );
    }

    private function percentage(int $value, int $total): int
    {
        return (int) min(100, max(0, round(($value / $total) * 100)));
    }

    private function recommendation(int $overall): string
    {
        return match (true) {
            $overall >= 85 => 'strong_match',
            $overall >= 70 => 'good_match',
            $overall >= 55 => 'weak_match',
            default => 'not_recommended',
        };
    }

    /**
     * @param array<int, string> $missingRequiredSkills
     */
    private function recommendationAction(int $overall, array $missingRequiredSkills, int $experienceScore): string
    {
        if ($overall >= 78 && count($missingRequiredSkills) <= 1 && $experienceScore >= 75) {
            return 'apply';
        }

        if ($overall >= 62 && count($missingRequiredSkills) <= 3) {
            return 'consider';
        }

        return 'skip';
    }
}
