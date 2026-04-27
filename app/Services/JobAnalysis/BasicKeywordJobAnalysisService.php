<?php

namespace App\Services\JobAnalysis;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;

class BasicKeywordJobAnalysisService implements JobAnalysisServiceInterface
{
    private const SKILLS = [
        'PHP', 'Laravel', 'CodeIgniter', 'Python', 'Django', 'FastAPI', 'REST APIs',
        'PostgreSQL', 'MySQL', 'Redis', 'OpenSearch', 'Elasticsearch', 'Docker',
        'AWS', 'CI/CD', 'Queues', 'RabbitMQ', 'SQS', 'System Design',
        'Clean Architecture', 'Vue.js', 'JavaScript', 'TypeScript', 'PHPUnit',
        'Pest', 'Kubernetes', 'Microservices', 'GraphQL',
    ];

    public function analyze(Job $job): array
    {
        $text = $this->text($job);
        $requiredSkills = $this->findSkills($text);
        $preferredSkills = $this->preferredSkills($text, $requiredSkills);
        $mustHaveSkills = $requiredSkills;
        $niceToHaveSkills = $preferredSkills;
        $seniority = $this->seniority($text);
        $roleType = $this->roleType($text);
        $domainTags = $this->domainTags($text);
        $techStack = $this->techStack($requiredSkills);
        $responsibilities = $this->responsibilities($job);

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
            'company_context' => $job->company_name ? "{$job->company_name} hiring context inferred from title and description." : null,
            'ai_summary' => $this->summary($job, $requiredSkills, $seniority, $roleType),
            'confidence_score' => 62,
        ];
    }

    private function text(Job $job): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            $job->title,
            $job->description_clean,
            $job->description_raw,
        ]))));
    }

    /**
     * @return array<int, string>
     */
    private function findSkills(string $text): array
    {
        return collect(self::SKILLS)
            ->filter(fn (string $skill): bool => str_contains($text, mb_strtolower($skill)))
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $requiredSkills
     * @return array<int, string>
     */
    private function preferredSkills(string $text, array $requiredSkills): array
    {
        $preferredSignals = ['nice to have', 'preferred', 'bonus', 'plus'];

        if (! collect($preferredSignals)->contains(fn (string $signal): bool => str_contains($text, $signal))) {
            return [];
        }

        return collect($requiredSkills)
            ->filter(fn (string $skill): bool => in_array($skill, ['Vue.js', 'JavaScript', 'TypeScript', 'Docker', 'AWS', 'Kubernetes'], true))
            ->values()
            ->all();
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
    private function summary(Job $job, array $skills, ?string $seniority, ?string $roleType): string
    {
        $skillText = $skills === [] ? 'general engineering skills' : implode(', ', array_slice($skills, 0, 6));
        $seniorityText = $seniority ?: 'unspecified seniority';
        $roleText = $roleType ?: 'software engineering';

        return "{$job->title} at {$job->company_name} appears to be a {$seniorityText} {$roleText} role focused on {$skillText}.";
    }
}
