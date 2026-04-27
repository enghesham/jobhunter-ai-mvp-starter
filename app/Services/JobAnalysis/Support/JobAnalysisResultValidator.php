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
        $mustHaveSkills = $this->sanitizeStringList($analysis['must_have_skills'] ?? null);
        $niceToHaveSkills = $this->sanitizeStringList($analysis['nice_to_have_skills'] ?? null);
        $domainTags = $this->sanitizeStringList($analysis['domain_tags'] ?? null, true);
        $techStack = $this->sanitizeStringList($analysis['tech_stack'] ?? null);
        $responsibilities = $this->sanitizeStringList($analysis['responsibilities'] ?? null);
        $seniority = $this->sanitizeNullableString($analysis['seniority'] ?? null);
        $roleType = $this->sanitizeNullableString($analysis['role_type'] ?? null);
        $companyContext = $this->sanitizeNullableString($analysis['company_context'] ?? null, 500);
        $summary = $this->sanitizeNullableString($analysis['ai_summary'] ?? null, 2000);
        $confidenceScore = $this->sanitizePercentage($analysis['confidence_score'] ?? null);

        if (
            $requiredSkills === null
            || $preferredSkills === null
            || $mustHaveSkills === null
            || $niceToHaveSkills === null
            || $domainTags === null
            || $techStack === null
            || $responsibilities === null
        ) {
            return null;
        }

        return [
            'required_skills' => $requiredSkills,
            'preferred_skills' => $preferredSkills,
            'must_have_skills' => $mustHaveSkills,
            'nice_to_have_skills' => $niceToHaveSkills,
            'seniority' => $seniority,
            'role_type' => $roleType,
            'domain_tags' => $domainTags,
            'tech_stack' => $techStack,
            'responsibilities' => $responsibilities,
            'company_context' => $companyContext,
            'ai_summary' => $summary,
            'confidence_score' => $confidenceScore ?? 0,
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

    private function sanitizePercentage(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $value)));
    }
}
