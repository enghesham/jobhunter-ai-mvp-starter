<?php

namespace App\Services\JobAnalysis\Support;

class JobAnalysisResultValidator
{
    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>|null
     */
    public function validate(array $analysis): ?array
    {
        $requiredSkills = $this->sanitizeStringList($analysis['required_skills'] ?? null);
        $preferredSkills = $this->sanitizeStringList($analysis['preferred_skills'] ?? null);
        $domainTags = $this->sanitizeStringList($analysis['domain_tags'] ?? null, true);
        $seniority = $this->sanitizeNullableString($analysis['seniority'] ?? null);
        $roleType = $this->sanitizeNullableString($analysis['role_type'] ?? null);
        $summary = $this->sanitizeNullableString($analysis['ai_summary'] ?? null, 2000);

        if ($requiredSkills === null || $preferredSkills === null || $domainTags === null) {
            return null;
        }

        return [
            'required_skills' => $requiredSkills,
            'preferred_skills' => $preferredSkills,
            'seniority' => $seniority,
            'role_type' => $roleType,
            'domain_tags' => $domainTags,
            'ai_summary' => $summary,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function sanitizeStringList(mixed $value, bool $lowercase = false): ?array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            return null;
        }

        $items = collect($value)
            ->filter(fn (mixed $item): bool => is_string($item))
            ->map(function (string $item) use ($lowercase): string {
                $normalized = trim($item);

                return $lowercase ? mb_strtolower($normalized) : $normalized;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items;
    }

    private function sanitizeNullableString(mixed $value, int $maxLength = 100): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
