<?php

namespace App\Services\JobAnalysis;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;

class BasicKeywordJobAnalysisService implements JobAnalysisServiceInterface
{
    private const SKILL_CATEGORIES = [
        'backend' => ['PHP', 'Laravel', 'CodeIgniter', 'Python', 'Django', 'FastAPI', 'REST APIs', 'GraphQL', 'Microservices'],
        'frontend' => ['Vue.js', 'JavaScript', 'TypeScript', 'React', 'Angular', 'HTML', 'CSS'],
        'database' => ['PostgreSQL', 'MySQL', 'Redis', 'OpenSearch', 'Elasticsearch', 'MongoDB'],
        'devops' => ['Docker', 'Kubernetes', 'CI/CD', 'Queues', 'RabbitMQ', 'SQS', 'System Design', 'Clean Architecture'],
        'cloud' => ['AWS', 'GCP', 'Azure', 'Cloud', 'Serverless', 'Terraform'],
        'soft_skills' => ['Communication', 'Leadership', 'Mentoring', 'Collaboration', 'Ownership', 'Problem Solving'],
    ];

    private const REQUIRED_SIGNALS = [
        'required', 'requirements', 'required qualifications', 'must have', 'must-have',
        'minimum qualifications', 'you have', 'what you bring', 'what we are looking for',
        'qualifications', 'experience with', 'expertise in',
    ];

    private const PREFERRED_SIGNALS = [
        'nice to have', 'nice-to-have', 'preferred', 'preferred qualifications',
        'bonus', 'plus', 'good to have', 'ideal', 'would be great',
    ];

    public function analyze(Job $job, bool $force = false): array
    {
        $text = $this->text($job);
        $requiredContext = $this->contextsForSignals($text, self::REQUIRED_SIGNALS);
        $preferredContext = $this->contextsForSignals($text, self::PREFERRED_SIGNALS);
        $allSkills = $this->findSkills($text);
        $preferredSkills = $this->findSkills($preferredContext);
        $requiredSkills = $this->requiredSkills($text, $allSkills, $preferredSkills, $requiredContext);
        $mustHaveSkills = $requiredSkills;
        $niceToHaveSkills = $preferredSkills;
        $seniority = $this->seniority($text);
        $roleType = $this->roleType($text);
        $yearsExperience = $this->yearsExperience($text);
        $workplaceType = $this->workplaceType($job, $text);
        $salary = $this->salary($job, $text);
        $locationHint = $this->locationHint($job, $text);
        $timezoneHint = $this->timezoneHint($text);
        $domainTags = $this->domainTags($text);
        $techStack = $this->techStack($requiredSkills);
        $skillCategories = $this->skillCategories($allSkills);
        $responsibilities = $this->responsibilities($job);

        return [
            'required_skills' => $requiredSkills,
            'preferred_skills' => $preferredSkills,
            'must_have_skills' => $mustHaveSkills,
            'nice_to_have_skills' => $niceToHaveSkills,
            'seniority' => $seniority,
            'role_type' => $roleType,
            'years_experience_min' => $yearsExperience['min'],
            'years_experience_max' => $yearsExperience['max'],
            'workplace_type' => $workplaceType,
            'salary_text' => $salary['text'],
            'salary_min' => $salary['min'],
            'salary_max' => $salary['max'],
            'salary_currency' => $salary['currency'],
            'location_hint' => $locationHint,
            'timezone_hint' => $timezoneHint,
            'domain_tags' => $domainTags,
            'tech_stack' => $techStack,
            'skill_categories' => $skillCategories,
            'responsibilities' => $responsibilities,
            'company_context' => $job->company_name ? "{$job->company_name} hiring context inferred from title and description." : null,
            'ai_summary' => $this->summary($job, $requiredSkills, $seniority, $roleType, $workplaceType, $yearsExperience['min']),
            'confidence_score' => $this->confidenceScore($requiredSkills, $preferredSkills, $yearsExperience, $workplaceType, $salary['text']),
        ];
    }

    private function text(Job $job): string
    {
        $raw = trim(implode("\n", array_filter([
            $job->title,
            $job->description_clean,
            $job->description_raw,
            $job->location,
            $job->salary_text,
        ])));

        $raw = html_entity_decode(strip_tags($raw));
        $raw = preg_replace('/[ \t]+/', ' ', $raw) ?? $raw;

        return mb_strtolower($raw);
    }

    /**
     * @return array<int, string>
     */
    private function findSkills(string $text): array
    {
        return collect($this->allSkills())
            ->filter(fn (string $skill): bool => str_contains($text, mb_strtolower($skill)))
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $allSkills
     * @param array<int, string> $preferredSkills
     * @return array<int, string>
     */
    private function requiredSkills(string $text, array $allSkills, array $preferredSkills, string $requiredContext): array
    {
        $requiredFromContext = $this->findSkills($requiredContext);

        if ($requiredFromContext !== []) {
            return collect(array_merge(
                $requiredFromContext,
                array_values(array_diff($allSkills, $preferredSkills))
            ))->unique()->values()->all();
        }

        return $allSkills;
    }

    private function seniority(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'principal') || str_contains($text, 'staff') => 'staff',
            str_contains($text, 'senior') || str_contains($text, 'sr.') => 'senior',
            str_contains($text, 'lead') => 'lead',
            str_contains($text, 'junior') || str_contains($text, 'entry') => 'junior',
            str_contains($text, 'mid') => 'mid',
            default => null,
        };
    }

    private function roleType(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'full stack') || str_contains($text, 'fullstack') => 'full_stack',
            str_contains($text, 'backend') || str_contains($text, 'back-end') || str_contains($text, 'api') => 'backend',
            str_contains($text, 'frontend') || str_contains($text, 'front-end') => 'frontend',
            str_contains($text, 'devops') || str_contains($text, 'platform') => 'platform',
            default => null,
        };
    }

    /**
     * @return array{min: int|null, max: int|null}
     */
    private function yearsExperience(string $text): array
    {
        $patterns = [
            '/(\d{1,2})\s*\+?\s*(?:-|to)\s*(\d{1,2})\s*\+?\s*years?(?: of)? experience/i',
            '/(?:minimum of|at least|minimum)\s+(\d{1,2})\s*\+?\s*years?(?: of)? experience/i',
            '/(\d{1,2})\s*\+?\s*years?(?: of)? experience/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $min = isset($matches[1]) ? (int) $matches[1] : null;
                $max = isset($matches[2]) ? (int) $matches[2] : null;

                return ['min' => $min, 'max' => $max];
            }
        }

        return ['min' => null, 'max' => null];
    }

    private function workplaceType(Job $job, string $text): ?string
    {
        if ($job->remote_type) {
            return $job->remote_type;
        }

        return match (true) {
            str_contains($text, 'hybrid') => 'hybrid',
            str_contains($text, 'remote') || str_contains($text, 'work from home') => 'remote',
            str_contains($text, 'on-site'), str_contains($text, 'onsite'), str_contains($text, 'in-office') => 'onsite',
            default => null,
        };
    }

    /**
     * @return array{text: string|null, min: int|null, max: int|null, currency: string|null}
     */
    private function salary(Job $job, string $text): array
    {
        if ($job->salary_text) {
            return [
                'text' => $job->salary_text,
                'min' => null,
                'max' => null,
                'currency' => $this->detectCurrency($job->salary_text),
            ];
        }

        $pattern = '/(?P<currency>\$|usd|eur|gbp|aed|sar)\s*(?P<min>\d[\d,\.]*)(?P<mink>k)?\s*(?:-|to)\s*(?P<max>\d[\d,\.]*)(?P<maxk>k)?/i';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return ['text' => null, 'min' => null, 'max' => null, 'currency' => null];
        }

        $rawText = trim((string) $matches[0]);
        $min = $this->normalizeSalaryNumber((string) $matches['min'], ! empty($matches['mink']));
        $max = $this->normalizeSalaryNumber((string) $matches['max'], ! empty($matches['maxk']));

        return [
            'text' => $rawText,
            'min' => $min,
            'max' => $max,
            'currency' => $this->normalizeCurrency((string) $matches['currency']),
        ];
    }

    private function locationHint(Job $job, string $text): ?string
    {
        if ($job->location) {
            return $job->location;
        }

        if (preg_match('/(?:location|based in|located in|must be in)\s*[:\-]?\s*([a-z0-9 ,\/()+-]{3,80})/i', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function timezoneHint(string $text): ?string
    {
        if (preg_match('/\b(utc[+\-]?\d{0,2}|gmt[+\-]?\d{0,2}|est|pst|cst|mst|cet|eet|european time(?:zone)?|us time(?:zone)?s?)\b/i', $text, $matches) === 1) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function domainTags(string $text): array
    {
        $tags = [
            'saas' => ['saas', 'b2b'],
            'fintech' => ['fintech', 'payments', 'banking'],
            'search' => ['search', 'opensearch', 'elasticsearch'],
            'ai' => ['ai', 'llm', 'machine learning'],
            'ecommerce' => ['commerce', 'shopify', 'marketplace'],
            'cloud' => ['aws', 'cloud', 'serverless'],
        ];

        return collect($tags)
            ->filter(fn (array $signals): bool => collect($signals)->contains(fn (string $signal): bool => str_contains($text, $signal)))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $skills
     * @return array<int, string>
     */
    private function techStack(array $skills): array
    {
        return collect($skills)
            ->filter(fn (string $skill): bool => in_array($skill, [
                'PHP', 'Laravel', 'CodeIgniter', 'Python', 'Django', 'FastAPI',
                'PostgreSQL', 'MySQL', 'Redis', 'OpenSearch', 'Elasticsearch',
                'Docker', 'AWS', 'Kubernetes', 'RabbitMQ', 'SQS', 'GraphQL',
            ], true))
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $skills
     * @return array<string, array<int, string>>
     */
    private function skillCategories(array $skills): array
    {
        return collect(self::SKILL_CATEGORIES)
            ->map(function (array $categorySkills) use ($skills): array {
                return array_values(array_filter($skills, fn (string $skill): bool => in_array($skill, $categorySkills, true)));
            })
            ->filter(fn (array $categorySkills): bool => $categorySkills !== [])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function responsibilities(Job $job): array
    {
        $text = trim((string) ($job->description_clean ?: $job->description_raw));

        if ($text === '') {
            return [];
        }

        return collect(preg_split('/(?<=[.!?])\s+/', $text) ?: [])
            ->map(fn (string $line): string => trim(strip_tags($line)))
            ->filter(fn (string $line): bool => $line !== '')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $skills
     */
    private function summary(Job $job, array $skills, ?string $seniority, ?string $roleType, ?string $workplaceType, ?int $yearsExperienceMin): string
    {
        $skillText = $skills === [] ? 'general engineering skills' : implode(', ', array_slice($skills, 0, 6));
        $seniorityText = $seniority ?: 'unspecified seniority';
        $roleText = $roleType ?: 'software engineering';
        $workplaceText = $workplaceType ? " with a {$workplaceType} setup" : '';
        $experienceText = $yearsExperienceMin ? " requiring about {$yearsExperienceMin}+ years of experience" : '';

        return "{$job->title} at {$job->company_name} appears to be a {$seniorityText} {$roleText} role{$workplaceText}{$experienceText}, focused on {$skillText}.";
    }

    /**
     * @param array{min: int|null, max: int|null} $yearsExperience
     */
    private function confidenceScore(array $requiredSkills, array $preferredSkills, array $yearsExperience, ?string $workplaceType, ?string $salaryText): int
    {
        $score = 58;
        $score += min(14, count($requiredSkills) * 2);
        $score += min(6, count($preferredSkills));
        $score += $yearsExperience['min'] !== null ? 6 : 0;
        $score += $workplaceType !== null ? 4 : 0;
        $score += $salaryText !== null ? 4 : 0;

        return min(88, $score);
    }

    private function contextsForSignals(string $text, array $signals): string
    {
        $segments = collect(preg_split('/[\r\n]+|(?<=[.!?])\s+/', $text) ?: [])
            ->map(fn (string $segment): string => trim($segment))
            ->filter()
            ->values();

        $context = [];

        foreach ($segments as $index => $segment) {
            foreach ($signals as $signal) {
                if (! str_contains($segment, $signal)) {
                    continue;
                }

                $slice = $segments->slice(max(0, $index), 3)->implode(' ');
                $context[] = $slice;
                break;
            }
        }

        return implode(' ', $context);
    }

    /**
     * @return array<int, string>
     */
    private function allSkills(): array
    {
        return collect(self::SKILL_CATEGORIES)
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    private function detectCurrency(string $text): ?string
    {
        if (preg_match('/\b(usd|eur|gbp|aed|sar)\b/i', $text, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if (str_contains($text, '$')) {
            return 'USD';
        }

        return null;
    }

    private function normalizeCurrency(string $currency): ?string
    {
        $currency = trim($currency);

        return $currency === '$' ? 'USD' : strtoupper($currency);
    }

    private function normalizeSalaryNumber(string $number, bool $isK): int
    {
        $value = (float) str_replace([',', ' '], '', $number);

        return (int) round($isK ? $value * 1000 : $value);
    }
}
