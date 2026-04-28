<?php

return [
    'ai_enabled' => filter_var(env('JOBHUNTER_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
    'ai_provider' => env('JOBHUNTER_AI_PROVIDER', 'null'),
    'ai_provider_chain' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBHUNTER_AI_PROVIDER_CHAIN', ''))))),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'ai_timeout' => env('JOBHUNTER_AI_TIMEOUT', 30),
    'ai_base_url' => env('JOBHUNTER_AI_BASE_URL'),
    'scan_hours' => env('JOBHUNTER_SCAN_HOURS', 6),
    'match_threshold' => env('JOBHUNTER_MATCH_THRESHOLD', 75),
    'allowed_sources' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBHUNTER_ALLOWED_SOURCES', 'custom,greenhouse,lever'))))),
    'pdf_driver' => env('JOBHUNTER_PDF_DRIVER', 'html'),
    'openai' => [
        'model' => env('JOBHUNTER_OPENAI_MODEL', 'gpt-4.1-mini'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('JOBHUNTER_GEMINI_MODEL', 'gemini-2.5-flash'),
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('JOBHUNTER_GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'base_url' => env('JOBHUNTER_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    ],
    'local_llm' => [
        'base_url' => env('JOBHUNTER_LOCAL_LLM_BASE_URL', 'http://127.0.0.1:11434/v1'),
        'model' => env('JOBHUNTER_LOCAL_LLM_MODEL', 'llama3.1'),
        'api_key' => env('JOBHUNTER_LOCAL_LLM_API_KEY'),
    ],
    'python_microservice' => [
        'base_url' => env('JOBHUNTER_PYTHON_AI_SERVICE_URL'),
        'api_key' => env('JOBHUNTER_PYTHON_AI_SERVICE_KEY'),
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
