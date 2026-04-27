# JobHunter AI MVP

Laravel-based backend for collecting jobs, analyzing them, matching them to a candidate profile, generating tailored resume drafts, and tracking applications.

## Frontend Setup
The repository now includes a separate Vue 3 frontend app in [frontend](C:/wamp64/www/jobhunter-ai-mvp-starter/frontend).

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Default frontend API base URL:

```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

## Implemented Modules
- Authentication with Laravel Sanctum
- Job sources
- Job ingestion
- Job analysis with AI provider abstraction and deterministic fallback
- Candidate profiles
- Matching
- Tailored resumes with HTML-first PDF-ready output
- Applications

## Core API Areas
- `/api/auth/*`
- `/api/job-sources`
- `/api/jobs`
- `/api/candidate-profiles`
- `/api/applications`
- `/api/answer-templates`
- `/api/jobhunter/*` mirrors the jobhunter-prefixed API surface

All jobhunter/business APIs are protected by `auth:sanctum`.

## Production Setup

### 1. Environment
Set at minimum:

```env
APP_NAME=JobHunterAI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=jobhunter_ai
DB_USERNAME=postgres
DB_PASSWORD=secret

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

JOBHUNTER_AI_PROVIDER=null
OPENAI_API_KEY=
JOBHUNTER_OPENAI_MODEL=gpt-4.1-mini
JOBHUNTER_BEDROCK_MODEL=anthropic.claude-3-5-sonnet
JOBHUNTER_SCAN_HOURS=6
JOBHUNTER_MATCH_THRESHOLD=75
JOBHUNTER_ALLOWED_SOURCES=custom,greenhouse,lever
JOBHUNTER_PDF_DRIVER=html
```

If using cookie-based SPA auth, also configure:

```env
SANCTUM_STATEFUL_DOMAINS=your-frontend.example
SESSION_DOMAIN=.your-domain.example
```

For token-based API clients, bearer tokens from Sanctum are sufficient.

### 2. Install and Generate
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Database
```bash
php artisan migrate --force
php artisan db:seed --force
```

For a clean local verification run:

```bash
php artisan migrate:fresh --seed
```

### 4. Storage
```bash
php artisan storage:link
```

Make sure the application can write to:
- `storage/`
- `bootstrap/cache/`

### 5. Queues
This project is queue-friendly. For production:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Recommended:
- run queue workers under Supervisor/systemd
- use `QUEUE_CONNECTION=database` or Redis
- monitor failed jobs

### 6. Scheduler
The scheduler runs:
- `jobhunter:scan-sources` every `JOBHUNTER_SCAN_HOURS`

Production cron entry:

```bash
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

### 7. Authentication
Public auth endpoints:
- `POST /api/auth/register`
- `POST /api/auth/login`

Protected auth endpoints:
- `GET /api/auth/me`
- `POST /api/auth/logout`

Use bearer tokens:

```http
Authorization: Bearer <sanctum-token>
```

## Queue and Scheduler Commands

### Scan active sources
```bash
php artisan jobhunter:scan-sources
php artisan jobhunter:scan-sources --sync
```

### Analyze pending jobs
```bash
php artisan jobhunter:analyze-pending
```

### Match pending jobs
```bash
php artisan jobhunter:match-pending
```

### Legacy targeted commands
```bash
php artisan jobs:scan
php artisan jobs:scan 1 --sync
php artisan jobs:analyze 1
php artisan jobs:match 1 1
```

## Deployment Checklist
- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Configure DB credentials
- Configure `QUEUE_CONNECTION`
- Configure `JOBHUNTER_AI_PROVIDER`
- Configure `OPENAI_API_KEY` only if using OpenAI
- Run migrations
- Link storage
- Start queue workers
- Start scheduler cron
- Cache config/routes/views
- Verify auth token flow
- Verify storage permissions

## Verification Commands
```bash
composer test
php artisan migrate:fresh --seed
php artisan route:list
php artisan queue:work
```

## Testing
```bash
composer test
php artisan test
php artisan test --filter=AuthAndOwnershipTest
php artisan test --filter=ResumeGenerationTest
```

## Notes
- AI provider failures are logged without exposing secrets.
- AI-heavy endpoints are rate-limited.
- Ownership is enforced by auth scoping plus policies.
- Current PDF output is HTML-first. The browsershot/playwright driver is intentionally stubbed until enabled.
