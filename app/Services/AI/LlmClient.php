<?php

namespace App\Services\AI;

class LlmClient
{
    public function analyzeJob(string $jobDescription): array
    {
        // TODO: replace this stub with OpenAI / Bedrock integration.
        return [
            'required_skills' => ['PHP', 'Laravel', 'REST APIs'],
            'preferred_skills' => ['PostgreSQL', 'Docker'],
            'seniority' => 'senior',
            'role_focus' => 'backend',
            'remote_type' => 'remote',
            'summary' => 'Senior backend role focused on Laravel and APIs.',
            'keywords' => ['scalable systems', 'backend', 'APIs'],
        ];
    }

    public function tailorResume(array $candidateProfile, array $jobAnalysis): array
    {
        return [
            'summary' => 'Senior Backend Engineer with strong Laravel and API architecture experience.',
            'reordered_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Redis', 'OpenSearch'],
            'highlighted_achievements' => [
                'Built scalable backend APIs for production platforms.',
                'Improved search and system performance using OpenSearch and database optimization.',
            ],
        ];
    }
}
