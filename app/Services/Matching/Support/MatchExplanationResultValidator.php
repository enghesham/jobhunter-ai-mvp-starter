<?php

namespace App\Services\Matching\Support;

class MatchExplanationResultValidator
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function validate(array $payload): ?array
    {
        $whyMatched = $this->sanitizeNullableString($payload['why_matched'] ?? null, 1500);
        $missingSkills = $this->sanitizeStringList($payload['missing_skills'] ?? null);
        $strengthAreas = $this->sanitizeStringList($payload['strength_areas'] ?? null);
        $riskFlags = $this->sanitizeStringList($payload['risk_flags'] ?? null);
        $resumeFocusPoints = $this->sanitizeStringList($payload['resume_focus_points'] ?? null);
        $summary = $this->sanitizeNullableString($payload['ai_recommendation_summary'] ?? null, 1500);
        $confidenceScore = $this->sanitizePercentage($payload['confidence_score'] ?? null);

        if (
            $missingSkills === null
            || $strengthAreas === null
            || $riskFlags === null
            || $resumeFocusPoints === null
        ) {
            return null;
        }

        return [
            'why_matched' => $whyMatched,
            'missing_skills' => $missingSkills,
            'strength_areas' => $strengthAreas,
            'risk_flags' => $riskFlags,
            'resume_focus_points' => $resumeFocusPoints,
            'ai_recommendation_summary' => $summary,
            'confidence_score' => $confidenceScore ?? 0,
        ];
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
