<?php

namespace App\Services\Matching;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;

class JobMatchScoringService
{
    /**
     * @return array<string, mixed>
     */
    public function score(CandidateProfile $profile, Job $job): array
    {
        $analysis = $job->analysis;
        $candidateSkills = $this->normalize($profile->core_skills ?? []);
        $requiredSkills = $this->normalize($analysis?->required_skills ?? []);
        $matchedSkills = array_values(array_intersect($candidateSkills, $requiredSkills));

        $skillScore = $this->percentage(count($matchedSkills), max(count($requiredSkills), 1));
        $titleScore = $this->titleScore($profile, $job);
        $seniorityScore = $this->seniorityScore($profile, $analysis?->seniority);
        $locationScore = $this->locationScore($profile, $job);
        $backendFocusScore = $analysis?->role_type === 'backend' ? 95 : ($analysis?->role_type === 'full_stack' ? 82 : 60);
        $domainScore = $this->domainScore($analysis?->domain_tags ?? []);

        $overall = (int) round(
            ($skillScore * 0.35)
            + ($titleScore * 0.20)
            + ($seniorityScore * 0.15)
            + ($locationScore * 0.10)
            + ($backendFocusScore * 0.10)
            + ($domainScore * 0.10)
        );

        return [
            'overall_score' => $overall,
            'title_score' => $titleScore,
            'skill_score' => $skillScore,
            'seniority_score' => $seniorityScore,
            'location_score' => $locationScore,
            'backend_focus_score' => $backendFocusScore,
            'domain_score' => $domainScore,
            'recommendation' => $this->recommendation($overall),
            'notes' => sprintf(
                'Matched skills: %s. Role type: %s. Seniority: %s.',
                $matchedSkills === [] ? 'none detected' : implode(', ', $matchedSkills),
                $analysis?->role_type ?: 'unknown',
                $analysis?->seniority ?: 'unknown',
            ),
        ];
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
    private function domainScore(array $domainTags): int
    {
        if (array_intersect($domainTags, ['saas', 'search', 'ai', 'cloud'])) {
            return 88;
        }

        return 75;
    }

    private function percentage(int $value, int $total): int
    {
        return (int) min(100, max(0, round(($value / $total) * 100)));
    }

    private function recommendation(int $overall): string
    {
        return match (true) {
            $overall >= 85 => 'strong_apply',
            $overall >= 70 => 'apply',
            $overall >= 55 => 'maybe',
            default => 'skip',
        };
    }
}
