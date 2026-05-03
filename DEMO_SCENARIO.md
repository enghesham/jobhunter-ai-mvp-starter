# Demo Scenario Flow

This demo dataset is designed for stabilization, walkthroughs, screenshots, and quick regression checks.

It does not require a live AI provider. The seeded data includes AI-like metadata, deterministic fallback examples, matches, resumes, application pipeline events, and application materials.

## Setup

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

Frontend:

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

## Demo Login

- Email: `demo@jobhunter.test`
- Password: `password`

## Seeded Scenario

The demo user includes:

- 1 active job source: `Demo Curated Backend Roles`
- 3 active live-safe RSS job sources for collection testing
- 2 inactive company-board templates for Greenhouse and Lever
- 1 candidate profile with experiences and projects
- 4 jobs across different fit levels
- 4 job analyses
- 4 match results
- 2 tailored resumes with stored HTML preview
- 3 tracked applications
- 3 application timeline/event histories
- 3 generated application materials for the strongest opportunity
- answer templates for cover letters and common application questions
- AI quality metadata across OpenRouter, Gemini, Groq, and deterministic fallback

## Walkthrough

### 1. Dashboard

Open `/dashboard`.

Expected:

- job source count is populated
- jobs count is populated
- analyzed/matched jobs are visible
- applications count is populated

### 2. Job Sources

Open `/job-sources`.

Expected:

- `Demo Curated Backend Roles` appears as active
- source type is `custom`
- source has seeded metadata
- RSS sources appear as active and can be used from `/opportunities` with `Collect Jobs`
- Greenhouse and Lever templates appear inactive until you replace their `base_url`/config with real public board values

### 3. Jobs

Open `/jobs`.

Expected jobs:

- `Senior Laravel Platform Engineer`
- `Backend API Engineer`
- `Full Stack Product Engineer`
- `Senior Cloud Infrastructure Engineer`

Recommended flow:

- open job details for `Senior Laravel Platform Engineer`
- review rich analysis fields
- review must-have and nice-to-have skills
- review AI metadata badges
- run Analyze with `force` only if you want to test live provider behavior

### 4. Matches

Open `/matches`.

Expected:

- 4 persisted matches
- strongest match: `Senior Laravel Platform Engineer`
- low-fit comparison: `Senior Cloud Infrastructure Engineer`

Recommended flow:

- open match details
- compare overall score, skill score, experience score, and recommendation
- review missing required skills and risk flags

### 5. Resumes

Open `/resumes`.

Expected:

- 2 generated tailored resumes
- HTML preview available
- PDF download works if a real PDF driver is configured, for example `JOBHUNTER_PDF_DRIVER=mpdf`

Recommended flow:

- preview the platform engineer resume
- review ATS keywords and warnings/gaps
- try PDF download if mPDF is enabled

### 6. Applications

Open `/applications`.

Expected:

- 3 applications:
  - `ready_to_apply`
  - `applied`
  - `interviewing`

Recommended flow:

- open application details for `Senior Laravel Platform Engineer`
- review timeline events
- review generated cover letter and answer materials
- test quick status update
- switch between table and board views if available

### 7. Settings

Open `/settings`.

Expected:

- answer templates exist for:
  - cover letter
  - why interested
  - about me
  - salary expectation
  - notice period
  - work authorization

Recommended flow:

- edit a template
- regenerate application materials from an application details page

### 8. AI Quality

Open `/developer/ai-quality`.

Expected:

- provider comparison shows seeded OpenRouter, Gemini, Groq, and deterministic fallback usage
- total runs should reflect analyses, matches, resumes, and materials
- fallback rate is not zero
- recent runs table is populated

## Stabilization Checklist

Run before a demo:

```bash
php artisan migrate:fresh --seed
php artisan test
cd frontend
npm run build
```

Manual checks:

- login works with demo credentials
- `/dashboard` loads counts
- `/jobs` shows 4 jobs
- `/matches` shows 4 matches
- `/resumes` shows 2 resumes
- `/applications` shows 3 applications
- `/developer/ai-quality` shows provider metrics
- resume HTML preview opens
- PDF download behavior matches configured PDF driver

## Notes

- The demo dataset is user-scoped and visible only to `demo@jobhunter.test`.
- The seeded AI results are deterministic demo records, not live provider calls.
- If you enable a real AI provider and force re-run actions, seeded AI metadata may be replaced by live provider output.
