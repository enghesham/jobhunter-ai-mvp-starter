<?php

namespace App\Services\Resume\Support;

class ResumeTailoringResultValidator
{
    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $allowedSkills
     * @param array<int, string> $allowedProjects
     * @param array<int, string> $allowedKeywords
     * @return array<string, mixed>|null
     */
    public function validate(
        array $payload,
        array $allowedSkills,
        array $allowedProjects,
        array $allowedKeywords,
        array $sourceExperienceBullets
    ): ?array {
        $headline = $this->sanitizeNullableString($payload['tailored_headline'] ?? null, 200);
        $summary = $this->sanitizeNullableString($payload['tailored_summary'] ?? null, 2000);
        $selectedSkills = $this->sanitizeRestrictedList($payload['selected_skills'] ?? null, $allowedSkills);
        $selectedProjects = $this->sanitizeRestrictedList($payload['selected_projects'] ?? null, $allowedProjects);
        $atsKeywords = $this->sanitizeRestrictedList($payload['ats_keywords'] ?? null, $allowedKeywords);
        $warnings = $this->sanitizeStringList($payload['warnings_or_gaps'] ?? null);
        $bullets = $this->sanitizeExperienceBullets($payload['tailored_experience_bullets'] ?? null, $sourceExperienceBullets);
        $confidenceScore = $this->sanitizePercentage($payload['confidence_score'] ?? null);

        if (
            $selectedSkills === null
            || $selectedProjects === null
            || $atsKeywords === null
            || $warnings === null
            || $bullets === null
        ) {
            return null;
        }

        return [
            'tailored_headline' => $headline,
            'tailored_summary' => $summary,
            'selected_skills' => $selectedSkills,
            'tailored_experience_bullets' => $bullets,
            'selected_projects' => $selectedProjects,
            'ats_keywords' => $atsKeywords,
            'warnings_or_gaps' => $warnings,
            'confidence_score' => $confidenceScore ?? 0,
        ];
    }

    /**
     * @param array<int, string> $allowedValues
     * @return array<int, string>|null
     */
    private function sanitizeRestrictedList(mixed $value, array $allowedValues): ?array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return null;
        }

        $allowed = collect($allowedValues)
            ->mapWithKeys(fn (string $item): array => [mb_strtolower(trim($item)) => $item]);

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->map(fn (string $item): ?string => $allowed->get(mb_strtolower($item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>|null
     */
    private function sanitizeExperienceBullets(mixed $value, array $sourceBullets): ?array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return null;
        }

        $normalizedSources = collect($sourceBullets)->map(fn (string $bullet): string => mb_strtolower($bullet))->all();

        $bullets = collect($value)
            ->filter(fn (mixed $item): bool => is_string($item))
            ->map(fn (string $item): string => trim(mb_substr($item, 0, 300)))
            ->filter()
            ->filter(function (string $item) use ($normalizedSources): bool {
                $lower = mb_strtolower($item);

                foreach ($normalizedSources as $source) {
                    similar_text($lower, $source, $similarity);

                    if ($similarity >= 45) {
                        return true;
                    }
                }

                return false;
            })
            ->unique()
            ->values()
            ->all();

        return $bullets;
    }

    /**
     * @return array<int, string>|null
     */
    private function sanitizeStringList(mixed $value): ?array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return null;
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item))
            ->map(fn (string $item): string => trim(mb_substr($item, 0, 250)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeNullableString(mixed $value, int $maxLength = 1000): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    private function sanitizePercentage(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $value)));
    }
}
