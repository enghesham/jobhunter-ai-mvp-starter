# JobHunter AI Frontend QA Checklist

## Auth
- Register a new user from `/register`
- Log in from `/login`
- Refresh the page and confirm the session restores
- Log out from the top bar user menu
- Confirm protected routes redirect to `/login` when unauthenticated

## Job Sources
- Create a job source
- Edit a job source
- Delete a job source
- Ingest at least one manual job
- Confirm invalid JSON shows inline validation

## Jobs
- Confirm jobs appear after ingestion
- Open job details
- Analyze a job
- Match a job
- Generate a resume

## Candidate Profiles
- Create a profile manually
- Import the sample profile JSON
- Edit a profile
- Delete a profile

## Matches
- Confirm a match created from `/jobs` appears in `/matches`
- Open match details and verify score breakdowns

## Resumes
- Confirm a generated resume appears in `/resumes`
- Open resume preview
- Open stored preview URL if available

## Applications
- Create an application manually
- Create an application from generated resume
- Edit an application
- Update application status from the table
- Delete an application

## Error And UX
- Confirm empty states show CTA buttons
- Confirm skeleton loaders show while data is loading
- Confirm 401 clears session and redirects to login
- Confirm network failures show retry-oriented messages
- Confirm validation failures do not expose backend traces
