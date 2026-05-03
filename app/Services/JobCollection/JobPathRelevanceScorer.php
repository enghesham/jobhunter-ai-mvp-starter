<?php

namespace App\Services\JobCollection;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\JobIngestion\DTO\NormalizedJobData;

class JobPathRelevanceScorer
{
    /**
     * @return array{score: int, reasons: array<int, string>, matched_keywords: array<int, string>, missing_keywords: array<int, string>}
     */
    public function score(Job|NormalizedJobData $job, ?JobPath $path, ?CandidateProfile $profile): array
    {
        $text = $this->jobText($job);
        $score = 0;
        $reasons = [];
        $matched = [];
        $excludeKeywords = $this->stringList($path?->exclude_keywords ?? ['translation', 'sales', 'cold calling', 'telesales']);
        $excluded = $this->matchedTerms($text, $excludeKeywords);

        if ($excluded !== []) {
            return [
                'score' => 0,
                'reasons' => ['Excluded by path keywords: '.implode(', ', $excluded)],
                'matched_keywords' => [],
                'missing_keywords' => $excluded,
            ];
        }

        $targetRoles = $this->stringList($path?->target_roles ?? $profile?->preferred_roles ?? [$profile?->primary_role, $profile?->headline]);
        $roleHits = $this->matchedTerms($text, $targetRoles);
        if ($roleHits !== []) {
            $score += min(30, count($roleHits) * 15);
            $matched = [...$matched, ...$roleHits];
            $reasons[] = 'Role match: '.implode(', ', array_slice($roleHits, 0, 3));
        }

        $requiredSkills = $this->stringList($path?->required_skills ?? $profile?->core_skills ?? []);
        $requiredHits = $this->matchedTerms($text, $requiredSkills);
        if ($requiredHits !== []) {
            $score += min(30, count($requiredHits) * 8);
            $matched = [...$matched, ...$requiredHits];
            $reasons[] = 'Required skills found: '.implode(', ', array_slice($requiredHits, 0, 4));
        }

        $keywordHits = $this->matchedTerms($text, $this->stringList($path?->include_keywords ?? []));
        if ($keywordHits !== []) {
            $score += min(20, count($keywordHits) * 5);
            $matched = [...$matched, ...$keywordHits];
            $reasons[] = 'Path keywords found: '.implode(', ', array_slice($keywordHits, 0, 4));
        }

        $optionalHits = $this->matchedTerms($text, $this->stringList($path?->optional_skills ?? $profile?->nice_to_have_skills ?? []));
        if ($optionalHits !== []) {
            $score += min(10, count($optionalHits) * 4);
            $matched = [...$matched, ...$optionalHits];
            $reasons[] = 'Optional strengths found: '.implode(', ', array_slice($optionalHits, 0, 3));
        }

        if ($this->locationMatches($job, $path, $profile)) {
            $score += 10;
            $reasons[] = 'Location or workplace preference matches.';
        }

        $missing = array_values(array_diff($requiredSkills, $requiredHits));

        return [
            'score' => max(0, min(100, $score)),
            'reasons' => $reasons === [] ? ['No strong Job Path signal was found.'] : array_values(array_unique($reasons)),
            'matched_keywords' => array_values(array_unique($matched)),
            'missing_keywords' => array_slice(array_values($missing), 0, 8),
        ];
    }

    private function jobText(Job|NormalizedJobData $job): string
    {
        $remoteType = $job instanceof Job ? $job->remote_type : $job->remoteType;
        $employmentType = $job instanceof Job ? $job->employment_type : $job->employmentType;
        $description = $job instanceof Job ? $job->description_raw : $job->descriptionRaw;
        $salaryText = $job instanceof Job ? $job->salary_text : $job->salaryText;

        return mb_strtolower(implode(' ', array_filter([
            $job instanceof Job ? $job->title : $job->title,
            $job instanceof Job ? $job->company_name : $job->companyName,
            $job->location,
            $remoteType,
            $employmentType,
            $job instanceof Job ? $job->description_clean : null,
            $description,
            $salaryText,
        ])));
    }

    /**
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private function matchedTerms(string $text, array $terms): array
    {
        return collect($terms)
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== '' && str_contains($text, mb_strtolower($term)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $value
        ))));
    }

    private function locationMatches(Job|NormalizedJobData $job, ?JobPath $path, ?CandidateProfile $profile): bool
    {
        $remoteType = $job instanceof Job ? $job->remote_type : $job->remoteType;
        $isRemote = $job instanceof Job ? (bool) $job->is_remote : $job->isRemote;

        if ($path?->remote_preference === 'remote' && ($isRemote || $remoteType === 'remote')) {
            return true;
        }

        if (in_array($path?->remote_preference, ['hybrid', 'any'], true) && in_array($remoteType, ['remote', 'hybrid'], true)) {
            return true;
        }

        $location = mb_strtolower($job->location ?: '');
        $preferredLocations = $this->stringList($path?->preferred_locations ?? $profile?->preferred_locations ?? []);

        return $location !== '' && collect($preferredLocations)->contains(
            fn (string $preferred): bool => $preferred !== '' && str_contains($location, mb_strtolower($preferred))
        );
    }
}
