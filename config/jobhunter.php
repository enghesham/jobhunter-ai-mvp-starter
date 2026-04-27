<?php

return [
    'ai_enabled' => filter_var(env('JOBHUNTER_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
    'ai_provider' => env('JOBHUNTER_AI_PROVIDER', 'null'),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'ai_timeout' => env('JOBHUNTER_AI_TIMEOUT', 30),
    'scan_hours' => env('JOBHUNTER_SCAN_HOURS', 6),
    'match_threshold' => env('JOBHUNTER_MATCH_THRESHOLD', 75),
    'allowed_sources' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBHUNTER_ALLOWED_SOURCES', 'custom,greenhouse,lever'))))),
    'pdf_driver' => env('JOBHUNTER_PDF_DRIVER', 'html'),
    'openai' => [
        'model' => env('JOBHUNTER_OPENAI_MODEL', 'gpt-4.1-mini'),
    ],
    'bedrock' => [
        'model' => env('JOBHUNTER_BEDROCK_MODEL', 'anthropic.claude-3-5-sonnet'),
    ],
    'prompts' => [
        'job_analysis' => <<<'PROMPT'
Analyze the job description and return strict JSON with:
required_skills[], preferred_skills[], seniority, role_focus, remote_type, summary, keywords[].
PROMPT,
        'match_scoring' => <<<'PROMPT'
Compare the candidate profile against the job analysis and return strict JSON with:
overall_score, strengths[], risks[], recommendation, reasoning.
PROMPT,
        'resume_tailoring' => <<<'PROMPT'
Rewrite the candidate summary and prioritize the most relevant achievements for this specific job.
Return strict JSON with: summary, reordered_skills[], highlighted_achievements[].
PROMPT,
    ],
];
