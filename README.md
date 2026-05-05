# JobHunter AI - Job Seeker Copilot

JobHunter AI is a Laravel + Vue application for building a guided **Job Seeker Copilot**.

The goal is not only to list jobs. The goal is to guide a job seeker from profile setup to application tracking:

- understand the user's career profile
- suggest practical job paths
- collect and filter relevant opportunities
- explain why a job fits or does not fit
- generate an apply package
- help the user track applications and follow-ups

The project is built to remain usable even when AI is unavailable. AI is the primary quality path for richer analysis and content generation, but deterministic fallback stays in place for safety, cost control, and continuity.

## Product Flow

The current product flow is centered around the job seeker journey:

1. Register or log in
2. Complete Guided Setup
3. Create or confirm My Career Profile
4. Select suggested Job Paths
5. Collect opportunities from safe job sources
6. Review Opportunities
7. Evaluate only the jobs the user cares about
8. Review Best Matches
9. Create an Apply Package
10. Manually apply outside the system
11. Mark the application as applied and track progress

The application does **not** auto-submit job applications.

### Guided Setup

First-time users are redirected to Guided Setup. The rest of the app is intentionally gated until onboarding is complete.

Guided Setup collects a compact set of career information:

- current or target role
- professional summary
- years of experience
- core skills
- preferred roles
- preferred locations
- preferred workplace type

The system then summarizes the profile and suggests 2-4 Job Paths. Users can accept, edit, or skip suggestions before entering the main product.

### My Career Profile

My Career Profile answers:

> What do I have?

It represents the user's professional background:

- role and headline
- summary
- years of experience
- skills and tools
- industries
- experiences and projects
- education and certifications
- languages
- salary and workplace preferences

Internally, this reuses the profile system while exposing a more user-friendly product concept.

### Job Paths

Job Paths answer:

> What am I looking for?

Examples:

- Senior Laravel Backend Remote
- PHP/Python API Developer
- Cloud-Native Backend Engineer
- Network Engineer in Gulf
- Product Designer Remote

A Job Path can be linked to a Career Profile. If it is linked, evaluations and apply packages use that profile by default. If it is not linked, the user's primary Career Profile is used.

Job Path rules include:

- target roles
- target domains
- include keywords
- exclude keywords
- required skills
- optional skills
- seniority levels
- preferred locations and countries
- preferred job types
- remote preference
- minimum relevance score
- minimum match score

### Opportunities

Opportunities are jobs that passed the first cheap relevance filter for the user's Job Paths.

Collection flow:

1. Active Job Path
2. Enabled safe job sources
3. Fetch external jobs
4. Normalize payloads
5. Deduplicate jobs
6. Score quick relevance against the Job Path
7. Store only relevant opportunities

The quick relevance score is not the final match score. It is only a low-cost pre-screening step used to avoid running AI on every collected job.

### Signals

Signals are short reasons explaining why a job appeared in Opportunities.

Examples:

- target role matched
- required skill matched
- include keyword matched
- remote preference matched
- location matched
- exclude keyword avoided

Signals are not a full AI explanation. They are quick relevance hints.

### Evaluate Fit

The user chooses which jobs to evaluate.

When the user clicks `Evaluate Fit`, the backend:

1. selects the linked Career Profile from the Job Path or falls back to the primary profile
2. analyzes the job description
3. calculates a match score
4. saves a Job Match record
5. updates the Opportunity as evaluated

This keeps cost and latency under control because AI-heavy work is only run on jobs the user actually wants to inspect.

### Best Matches

Best Matches are evaluated Opportunities that pass the Job Path's minimum match score or receive an apply recommendation.

Default sorting:

1. match score descending
2. posted date descending

Duplicate jobs are hidden by default. Hidden or not-relevant jobs are also hidden unless the user explicitly requests full review mode.

### Apply Package

An Apply Package prepares the material the user needs to apply manually:

- tailored resume or CV
- cover letter
- short answer templates
- salary expectation answer
- notice period answer
- why interested answer
- strengths for this role
- missing skills warning
- interview prep questions
- follow-up email template

The user can choose which package sections to generate to reduce AI cost and waiting time. Generated content is saved and editable.

### My Applications

Applications track the user's manual application workflow:

- draft
- ready to apply
- applied
- interviewing
- rejected
- offer
- archived

Applications can connect to:

- job
- career profile
- job path
- apply package
- status timeline events

## Demo Readiness

The project includes a complete seeded demo scenario for stabilization and walkthroughs.

Run:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

Demo login:

- Email: `demo@jobhunter.test`
- Password: `password`

The seeded scenario includes onboarding-ready user data, Career Profiles, Job Paths, job sources, collected/demo jobs, Opportunities, analyses, matches, Apply Packages, tailored resumes, applications, timeline events, application materials, answer templates, and AI quality metadata.

`JobSourceSeeder` also adds live-safe collection sources for the demo user:

- active RSS sources for remote/software jobs
- inactive Greenhouse and Lever templates you can customize and activate

After seeding, you can test collection with:

```bash
php artisan jobhunter:collect-jobs --user=1 --sync
```

Full walkthrough:

- `DEMO_SCENARIO.md`

## Current Scope

### Backend
- Laravel API with Sanctum authentication
- Ownership-scoped data per user
- Guided onboarding
- Career Profile management
- Job Path management
- Opportunity generation and evaluation
- Best Match filtering
- Job sources CRUD
- Job collection from safe sources
- Manual job ingestion
- Job analysis
- Candidate profile compatibility layer
- Matching with explanation
- Apply Package generation
- Tailored resume generation
- Application tracking
- Application timeline events
- Answer templates
- AI quality dashboard
- Queue-friendly jobs and scheduler commands

### Frontend
- Vue 3 + Vite + TypeScript
- PrimeVue + Tailwind CSS
- Auth flow
- Guided Setup
- Dashboard
- My Career Profile
- Job Paths
- Opportunities
- Best Matches
- Apply Packages
- My Applications
- Developer AI Quality dashboard
- Legacy/admin screens for job sources, jobs, matches, resumes, and candidate profiles where useful

## AI Strategy

The application is designed as `AI-ready`, not `AI-dependent`.

- AI is used for:
  - onboarding Job Path suggestions
  - job analysis
  - match explanation
  - apply package content
  - resume tailoring
- deterministic services remain available as fallback
- provider failures, invalid JSON, timeouts, and unavailable models do not break the workflow

### Current AI Behavior

Each AI operation follows this pattern:

1. Build a structured prompt
2. Call the configured AI provider
3. Validate and normalize the JSON result
4. Persist enriched metadata
5. Fall back to deterministic logic if AI fails

### Matching Behavior

The core match score is deterministic and explainable. AI enriches analysis and explanations, but the scoring engine remains rule-based for stability.

The matcher considers:

- required and preferred skills
- years of experience
- title and role alignment
- seniority alignment
- location and workplace preference
- backend/domain focus
- Job Path relevance

The match result can include:

- `overall_score`
- `skills_score`
- `experience_score`
- `seniority_score`
- `location_score`
- `domain_score`
- `missing_required_skills`
- `nice_to_have_gaps`
- `strength_areas`
- `risk_flags`
- `resume_focus_points`
- `recommendation`

If a Job Path is linked to the Opportunity, the match uses that Job Path and its linked Career Profile. If no Job Path is available, the user's primary Career Profile is used.

### AI Metadata

Analysis, match explanation, apply packages, and resumes store metadata such as:

- `ai_provider`
- `ai_model`
- `prompt_version`
- `ai_duration_ms`
- `fallback_used`
- `ai_generated_at`
- `ai_confidence_score`

### AI Caching

The project caches AI-derived results using an `input_hash` and `prompt_version`.

- repeated job analysis with unchanged input reuses the stored record
- repeated resume generation with unchanged input and same version reuses the stored version
- repeated match explanation with unchanged input reuses the stored explanation

### AI Quality Dashboard

The frontend includes a developer dashboard at:

- `/developer/ai-quality`

Backend endpoint:

- `GET /api/jobhunter/ai-quality`

The dashboard reads persisted AI metadata plus sanitized Laravel log signals to show:

- AI success vs deterministic fallback usage
- average duration per provider and operation
- average confidence score
- cache hit rate from logged AI cache events
- duplicated `input_hash` records that indicate reuse potential
- top sanitized provider errors
- provider comparison across OpenRouter, Gemini, Groq, OpenAI, local providers, and fallback

Provider error messages are sanitized before being returned to the frontend. API keys and bearer tokens must not be exposed in the dashboard.

Optional environment toggle:

```env
JOBHUNTER_AI_QUALITY_DASHBOARD_ENABLED=true
```

Set it to `false` if you want to hide the developer dashboard in a shared production environment.

### Force Re-run

Caching can be bypassed when needed.

Use `force=true` when you want to:

- test a changed prompt
- test a changed model
- refresh a stale result
- verify a provider change without changing input data

Supported endpoints:

- `POST /api/jobhunter/jobs/{job}/analyze`
- `POST /api/jobhunter/jobs/{job}/match`
- `POST /api/jobhunter/jobs/{job}/generate-resume`

Example:

```json
{
  "profile_id": 1,
  "force": true
}
```

## AI Provider Architecture

The project uses an internal provider contract:

- `AiProviderInterface`
- `AiProviderManager`

Implemented providers:

- `NullAiProvider`
- `OpenAiProvider`
- `OpenRouterProvider`
- `GeminiProvider`
- `GroqProvider`
- `LocalLlmProvider`
- `PythonMicroserviceProvider`
- `BedrockProvider` stub

This keeps the business layer independent from any one provider. The application can later use hosted APIs, local models, or a Python microservice without rewriting the analysis, matching, or resume services.

### Recommended Current Provider

For a lightweight machine and free/cheap initial usage, the current recommended setup is:

- `openrouter` as the active provider
- deterministic fallback enabled by design

Local LLM inference is supported, but it is not recommended as the primary provider on lower-spec hardware.

## Repository Structure

### Backend

- `app/Modules/*` domain modules and HTTP layer
- `app/Services/*` business services and AI integration
- `app/Jobs/*` queue-friendly background jobs
- `config/jobhunter.php` project-specific configuration
- `routes/api.php` API surface
- `routes/console.php` artisan commands and scheduler

### Frontend

The frontend lives in `frontend/` at the repository root.

Key frontend areas:

- `frontend/src/app`
- `frontend/src/layouts`
- `frontend/src/modules`
- `frontend/src/shared`

## API Surface

The canonical business API surface is:

- `/api/jobhunter/...`

Authentication remains under:

- `/api/auth/...`

### Auth Endpoints

Public:

- `POST /api/auth/register`
- `POST /api/auth/login`

Protected:

- `GET /api/auth/me`
- `POST /api/auth/logout`

All business endpoints require `auth:sanctum`.

Use bearer tokens:

```http
Authorization: Bearer <token>
Accept: application/json
```

### Main Business Endpoints

- `GET /api/jobhunter/onboarding`
- `POST /api/jobhunter/onboarding/career-profile`
- `POST /api/jobhunter/onboarding/suggest-job-paths`
- `POST /api/jobhunter/onboarding/complete`
- `GET|POST|PUT|PATCH|DELETE /api/jobhunter/career-profiles`
- `POST /api/jobhunter/career-profiles/{id}/make-primary`
- `GET|POST|PUT|PATCH|DELETE /api/jobhunter/job-paths`
- `POST /api/jobhunter/job-paths/{id}/collect`
- `GET /api/jobhunter/opportunities`
- `POST /api/jobhunter/opportunities/refresh`
- `POST /api/jobhunter/opportunities/{id}/evaluate`
- `POST /api/jobhunter/opportunities/{id}/hide`
- `POST /api/jobhunter/opportunities/{id}/restore`
- `GET /api/jobhunter/job-collection/runs`
- `POST /api/jobhunter/job-collection/collect-due`
- `GET|POST|PUT|PATCH|DELETE /api/jobhunter/job-sources`
- `POST /api/jobhunter/job-sources/{id}/scan`
- `POST /api/jobhunter/job-sources/{id}/ingest`
- `GET /api/jobhunter/jobs`
- `GET /api/jobhunter/jobs/{id}`
- `GET /api/jobhunter/jobs/{id}/analysis`
- `POST /api/jobhunter/jobs/{id}/analyze`
- `POST /api/jobhunter/jobs/{id}/match`
- `POST /api/jobhunter/jobs/{id}/apply-package`
- `GET /api/jobhunter/matches`
- `GET /api/jobhunter/matches/{id}/explanation`
- `GET /api/jobhunter/ai-quality`
- `POST /api/jobhunter/jobs/{id}/generate-resume`
- `GET /api/jobhunter/resumes`
- `GET /api/jobhunter/resumes/{id}`
- `GET /api/jobhunter/resumes/{id}/download-pdf`
- `GET /api/jobhunter/apply-packages`
- `GET /api/jobhunter/apply-packages/{id}`
- `PATCH /api/jobhunter/apply-packages/{id}`
- `POST /api/jobhunter/apply-packages/{id}/create-application`
- `GET|POST|PUT|PATCH|DELETE /api/jobhunter/candidate-profiles`
- `POST /api/jobhunter/candidate-profiles/import`
- `GET|POST|PUT|PATCH|DELETE /api/jobhunter/applications`
- `POST /api/jobhunter/applications/{id}/events`
- `GET /api/jobhunter/applications/{id}/materials`
- `POST /api/jobhunter/applications/{id}/generate-materials`
- `POST /api/jobhunter/answer-templates/bootstrap-defaults`
- `GET|POST|PUT|PATCH|DELETE /api/jobhunter/answer-templates`

### Response Shape

The API returns normalized JSON responses in this shape:

```json
{
  "success": true,
  "data": {}
}
```

Paginated lists return:

```json
{
  "success": true,
  "data": {
    "data": [],
    "links": {},
    "meta": {}
  }
}
```

## Local Development Setup

### Backend

1. Install dependencies:

```bash
composer install
```

2. Create environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database in `.env`

The project works best with PostgreSQL for real usage. Example:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=job_hunter
DB_USERNAME=postgres
DB_PASSWORD=root
```

4. Run database setup:

```bash
php artisan migrate
php artisan db:seed
```

5. Link storage:

```bash
php artisan storage:link
```

6. Start the backend:

```bash
php artisan serve
```

### Frontend Setup

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Default frontend API base:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

### Key Frontend Routes

- `/onboarding` - Guided Setup for first-time users
- `/dashboard` - high-level user workspace
- `/opportunities` - collected jobs and manual evaluation flow
- `/matches` - Best Matches and match history/details
- `/apply-packages` - saved application content packages
- `/applications` - application tracker and status timeline
- `/job-sources` - source management for job collection
- `/jobs` - legacy/admin job list and direct job actions
- `/candidate-profile` - compatibility screen for candidate profiles
- `/resumes` - generated resume history and previews
- `/developer/ai-quality` - AI quality and fallback dashboard

## AI Configuration

### Minimal OpenRouter Setup

```env
JOBHUNTER_AI_ENABLED=true
JOBHUNTER_AI_PROVIDER=openrouter
JOBHUNTER_AI_PROVIDER_CHAIN=openrouter
OPENROUTER_API_KEY=
JOBHUNTER_OPENROUTER_MODEL=openrouter/auto
JOBHUNTER_OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
JOBHUNTER_AI_TIMEOUT=30
JOBHUNTER_AI_CACHE_ENABLED=true
JOBHUNTER_AI_QUALITY_DASHBOARD_ENABLED=true
JOBHUNTER_ANALYSIS_PROMPT_VERSION=v1
JOBHUNTER_MATCH_PROMPT_VERSION=v1
JOBHUNTER_RESUME_PROMPT_VERSION=v1
```

### Disable AI Completely

```env
JOBHUNTER_AI_ENABLED=false
JOBHUNTER_AI_PROVIDER=null
JOBHUNTER_AI_PROVIDER_CHAIN=
```

### Optional Providers

Supported environment variables include:

- `OPENAI_API_KEY`
- `GEMINI_API_KEY`
- `GROQ_API_KEY`
- `JOBHUNTER_LOCAL_LLM_BASE_URL`
- `JOBHUNTER_LOCAL_LLM_MODEL`
- `JOBHUNTER_PYTHON_AI_SERVICE_URL`
- `JOBHUNTER_PYTHON_AI_SERVICE_KEY`

## Job Collection

The production-safe collection flow is Job Path first:

1. Active Job Path
2. Fetch jobs from enabled safe sources
3. Normalize the external payload
4. Apply cheap Job Path relevance filtering
5. Deduplicate using existing job hashes/fingerprints
6. Create Opportunities for the user to evaluate manually

This flow does not run AI analysis or matching automatically by default. AI-heavy evaluation still happens only when the user chooses `Evaluate Fit`.

### Collection Inputs

Collection uses the user's Job Paths and Career Profiles:

- `target_roles` decide which titles and roles are relevant
- `include_keywords` boost relevant jobs
- `exclude_keywords` reject unrelated jobs early
- `required_skills` and `optional_skills` improve relevance scoring
- `preferred_locations`, `preferred_countries`, and `remote_preference` filter workplace fit
- `min_relevance_score` decides whether a job should become an Opportunity

The linked Career Profile is used when a Job Path has one. Otherwise, the primary Career Profile is used.

### Accepted vs Filtered Jobs

Fetched jobs can end in one of these states:

- accepted and stored as opportunities
- updated because a duplicate already exists
- filtered because relevance is below the threshold
- skipped because an exclude keyword matched
- skipped because the payload is invalid or unsupported

This keeps the database focused on relevant jobs and avoids spending AI calls on weak matches.

### Evaluation Policy

The default policy is user-controlled evaluation:

- collected jobs are cheap-filtered only
- the user picks which jobs to evaluate
- `Evaluate Fit` runs job analysis and matching
- low-fit evaluated jobs can still be continued manually if the user wants
- Best Matches only shows evaluated jobs that pass the configured match threshold

Safe source types:

- `rss`
- `greenhouse`
- `lever`

Avoid aggressive scraping, anti-bot bypassing, private-data collection, and auto-submitting applications. For LinkedIn and Indeed, prefer manual URL import, a browser extension, or an external provider that is legally and technically acceptable.

Collection environment:

```env
JOBHUNTER_ALLOWED_SOURCES=custom,rss,greenhouse,lever
JOBHUNTER_COLLECTION_SAFE_SOURCE_TYPES=rss,greenhouse,lever
JOBHUNTER_COLLECTION_FETCH_TIMEOUT=20
JOBHUNTER_COLLECTION_MAX_ACCEPT_THRESHOLD=55
JOBHUNTER_COLLECTION_STORE_BELOW_THRESHOLD=false
JOBHUNTER_COLLECTION_SCHEDULE_EVERY_MINUTES=15
```

Collection APIs:

- `POST /api/jobhunter/job-paths/{jobPath}/collect`
- `POST /api/jobhunter/job-collection/collect-due`
- `GET /api/jobhunter/job-collection/runs`

## Useful Artisan Commands

### Scanning

```bash
php artisan jobs:scan
php artisan jobs:scan 1 --sync
php artisan jobhunter:scan-sources
php artisan jobhunter:scan-sources --sync
php artisan jobhunter:collect-jobs
php artisan jobhunter:collect-jobs --sync
php artisan jobhunter:collect-jobs --all-active --sync
php artisan jobhunter:collect-jobs --path=1 --sync
```

### Analysis and Matching

```bash
php artisan jobs:analyze 1
php artisan jobs:match 1 1
php artisan jobhunter:analyze-pending
php artisan jobhunter:match-pending
```

### AI Health

```bash
php artisan jobhunter:ai-health
php artisan jobhunter:ai-health --json
```

### Testing

```bash
php artisan test
composer test
```

## Queues and Scheduler

The project is queue-friendly, but most MVP API flows are safe to run synchronously.

### Queue Worker

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

### Scheduler

The scheduler runs:

- `jobhunter:scan-sources` every `JOBHUNTER_SCAN_HOURS`
- `jobhunter:collect-jobs` every `JOBHUNTER_COLLECTION_SCHEDULE_EVERY_MINUTES`

Production cron:

```bash
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

## Apply Packages

Apply Packages are generated after the user evaluates a job and decides it is worth applying to.

An Apply Package can include:

- tailored resume content
- cover letter
- reusable application answers
- salary expectation answer
- notice period answer
- why interested answer
- strengths for the role
- missing skills and gap warnings
- interview prep questions
- follow-up email template

The user can generate all sections or only selected sections. This reduces AI cost and avoids generating content the user does not need.

Package generation follows the same AI-safe pattern used elsewhere:

1. Build content from the job, match result, Career Profile, and Job Path
2. Use AI when enabled and available
3. Validate and normalize the generated content
4. Fall back to deterministic package content if AI fails
5. Save the generated package for later viewing and editing

Apply Packages can create Application records, but the system never submits an application automatically.

## Resume Output

Resume generation supports:

- structured tailored content
- stored HTML output
- HTML preview via storage
- real PDF export through either a PHP-only driver or a browser-based driver

### PDF Drivers

Supported drivers:

- `html`
- `mpdf`
- `browsershot`
- `playwright`

Recommended production driver:

- `mpdf`

This is the most practical general-purpose production setup because it does not require Chrome, Edge, or a headless browser binary on the server.

Recommended environment:

```env
JOBHUNTER_PDF_DRIVER=mpdf
JOBHUNTER_MPDF_TEMP_DIR=/tmp/jobhunter-mpdf
JOBHUNTER_PDF_TIMEOUT=60
```

Browser-based drivers are still available when you want HTML-to-PDF fidelity closer to the browser preview.

The `browsershot` and `playwright` values currently use the same headless-browser implementation and require a local browser executable such as Edge or Chrome.

Example environment for browser-based rendering:

```env
JOBHUNTER_PDF_DRIVER=browsershot
JOBHUNTER_PDF_BROWSER_PATH=/usr/bin/chromium-browser
JOBHUNTER_PDF_TIMEOUT=60
```

If `JOBHUNTER_PDF_DRIVER=html`, the app still stores HTML preview output, but `download-pdf` will return an error until a real PDF driver is enabled.

### Resume PDF Download

Download endpoint:

```http
GET /api/jobhunter/resumes/{resume}/download-pdf
Authorization: Bearer <token>
```

## Ownership and Security

- business APIs are protected by Sanctum
- ownership is enforced in controllers, scoped queries, and policies
- users only see their own:
  - job sources
  - candidate profiles
  - jobs
  - matches
  - resumes
  - applications

## Production Notes

Recommended production baseline:

- `APP_ENV=production`
- `APP_DEBUG=false`
- PostgreSQL
- queue worker under Supervisor/systemd
- scheduler enabled
- writable `storage/` and `bootstrap/cache/`
- cached config/routes/views

### Production Checklist

- configure environment variables
- run migrations
- run seeders if desired
- link storage
- run queue worker
- enable scheduler
- verify Sanctum token flow
- verify AI provider health
- verify file permissions

## Verification Commands

```bash
php artisan migrate:fresh --seed
php artisan route:list
php artisan test
php artisan jobhunter:ai-health --json
cd frontend && npm run build
```

## Notes and Limitations

- the business API surface is standardized under `/api/jobhunter/...`
- AI provider failures are logged safely without exposing secrets
- AI-heavy endpoints are rate-limited
- PDF output is still HTML-first unless you enable a real PDF backend
- `BedrockProvider` remains a stub
- local PHP in this environment may show an Xdebug DLL warning; this is environment-specific and not a project bug
