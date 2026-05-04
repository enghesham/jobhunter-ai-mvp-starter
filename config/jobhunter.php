<?php

return [
    'ai_enabled' => filter_var(env('JOBHUNTER_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
    'ai_provider' => env('JOBHUNTER_AI_PROVIDER', 'null'),
    'ai_provider_chain' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBHUNTER_AI_PROVIDER_CHAIN', ''))))),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'ai_timeout' => env('JOBHUNTER_AI_TIMEOUT', 30),
    'ai_base_url' => env('JOBHUNTER_AI_BASE_URL'),
    'ai_cache_enabled' => filter_var(env('JOBHUNTER_AI_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
    'ai_quality_dashboard_enabled' => filter_var(env('JOBHUNTER_AI_QUALITY_DASHBOARD_ENABLED', true), FILTER_VALIDATE_BOOL),
    'scan_hours' => env('JOBHUNTER_SCAN_HOURS', 6),
    'match_threshold' => env('JOBHUNTER_MATCH_THRESHOLD', 75),
    'opportunities' => [
        'max_jobs_per_refresh' => (int) env('JOBHUNTER_OPPORTUNITY_MAX_JOBS_PER_REFRESH', 200),
        'default_min_relevance_score' => (int) env('JOBHUNTER_OPPORTUNITY_MIN_RELEVANCE_SCORE', 45),
        'store_below_threshold' => filter_var(env('JOBHUNTER_OPPORTUNITY_STORE_BELOW_THRESHOLD', false), FILTER_VALIDATE_BOOL),
        'auto_ai_evaluation_enabled' => filter_var(env('JOBHUNTER_OPPORTUNITY_AUTO_AI_EVALUATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'max_ai_evaluations_per_refresh' => (int) env('JOBHUNTER_OPPORTUNITY_MAX_AI_EVALUATIONS_PER_REFRESH', 0),
    ],
    'allowed_sources' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBHUNTER_ALLOWED_SOURCES', 'custom,rss,greenhouse,lever'))))),
    'collection' => [
        'safe_source_types' => array_values(array_filter(array_map('trim', explode(',', (string) env('JOBHUNTER_COLLECTION_SAFE_SOURCE_TYPES', 'rss,greenhouse,lever'))))),
        'fetch_timeout' => (int) env('JOBHUNTER_COLLECTION_FETCH_TIMEOUT', 20),
        'max_accept_threshold' => (int) env('JOBHUNTER_COLLECTION_MAX_ACCEPT_THRESHOLD', 55),
        'store_below_threshold' => filter_var(env('JOBHUNTER_COLLECTION_STORE_BELOW_THRESHOLD', false), FILTER_VALIDATE_BOOL),
        'schedule_every_minutes' => (int) env('JOBHUNTER_COLLECTION_SCHEDULE_EVERY_MINUTES', 15),
    ],
    'pdf_driver' => env('JOBHUNTER_PDF_DRIVER', 'html'),
    'pdf' => [
        'browser_path' => env('JOBHUNTER_PDF_BROWSER_PATH'),
        'timeout' => (int) env('JOBHUNTER_PDF_TIMEOUT', 60),
        'mpdf_temp_dir' => env('JOBHUNTER_MPDF_TEMP_DIR', storage_path('app/tmp/mpdf')),
    ],
    'openai' => [
        'model' => env('JOBHUNTER_OPENAI_MODEL', 'gpt-4.1-mini'),
    ],
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('JOBHUNTER_OPENROUTER_MODEL', 'openrouter/auto'),
        'base_url' => env('JOBHUNTER_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
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
    'ai_operations' => [
        'analysis' => [
            'prompt_version' => env('JOBHUNTER_ANALYSIS_PROMPT_VERSION', 'v1'),
        ],
        'match_explanation' => [
            'prompt_version' => env('JOBHUNTER_MATCH_PROMPT_VERSION', 'v1'),
        ],
        'resume_tailoring' => [
            'prompt_version' => env('JOBHUNTER_RESUME_PROMPT_VERSION', 'v1'),
        ],
        'application_materials' => [
            'prompt_version' => env('JOBHUNTER_APPLICATION_MATERIALS_PROMPT_VERSION', 'v1'),
        ],
        'apply_package' => [
            'prompt_version' => env('JOBHUNTER_APPLY_PACKAGE_PROMPT_VERSION', 'v1'),
        ],
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
