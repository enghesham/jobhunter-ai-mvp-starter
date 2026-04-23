<?php

return [
    'scan_hours' => env('JOBHUNTER_SCAN_HOURS', 6),
    'match_threshold' => env('JOBHUNTER_MATCH_THRESHOLD', 75),
    'llm' => [
        'provider' => env('JOBHUNTER_LLM_PROVIDER', 'openai'),
        'model' => env('JOBHUNTER_LLM_MODEL', 'gpt-4.1-mini'),
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
