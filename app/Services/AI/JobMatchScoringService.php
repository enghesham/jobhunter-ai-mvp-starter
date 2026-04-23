<?php

namespace App\Services\AI;

class JobMatchScoringService
{
    public function score(array $candidateProfile, array $jobAnalysis): array
    {
        $candidateSkills = array_map('mb_strtolower', $candidateProfile['core_skills'] ?? []);
        $requiredSkills = array_map('mb_strtolower', $jobAnalysis['required_skills'] ?? []);

        $matchedSkills = count(array_intersect($candidateSkills, $requiredSkills));
        $requiredCount = max(count($requiredSkills), 1);
        $skillScore = (int) round(($matchedSkills / $requiredCount) * 100);

        $headline = mb_strtolower($candidateProfile['headline'] ?? '');
        $roleFocus = mb_strtolower($jobAnalysis['role_focus'] ?? 'backend');
        $titleScore = str_contains($headline, 'backend') && $roleFocus === 'backend' ? 95 : 70;
        $seniorityScore = ($jobAnalysis['seniority'] ?? 'mid') === 'senior' ? 90 : 75;
        $locationScore = 90;
        $backendFocusScore = $roleFocus === 'backend' ? 95 : 60;
        $domainScore = 80;

        $overall = (int) round(
            ($skillScore * 0.30)
            + ($titleScore * 0.20)
            + ($seniorityScore * 0.15)
            + ($locationScore * 0.10)
            + ($backendFocusScore * 0.15)
            + ($domainScore * 0.10)
        );

        return [
            'overall_score' => $overall,
            'skill_score' => $skillScore,
            'title_score' => $titleScore,
            'seniority_score' => $seniorityScore,
            'location_score' => $locationScore,
            'backend_focus_score' => $backendFocusScore,
            'domain_score' => $domainScore,
            'recommendation' => $overall >= 85 ? 'strong_apply' : ($overall >= 70 ? 'apply' : 'skip'),
            'notes' => sprintf('Matched %d of %d required skills.', $matchedSkills, $requiredCount),
        ];
    }
}
