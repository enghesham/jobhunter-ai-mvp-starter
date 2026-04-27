<?php

namespace App\Services\Applications;

use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): Application
    {
        $data = $this->validatedPayload($payload);

        return Application::create($data);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(Application $application, array $payload): Application
    {
        $data = $this->validatedPayload($payload + [
            'job_id' => $payload['job_id'] ?? $application->job_id,
            'profile_id' => $payload['profile_id'] ?? $application->profile_id,
            'tailored_resume_id' => $payload['tailored_resume_id'] ?? $application->tailored_resume_id,
            'user_id' => $payload['user_id'] ?? $application->user_id,
        ]);

        $application->update($data);

        return $application->fresh(['job', 'profile', 'tailoredResume']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validatedPayload(array $payload): array
    {
        /** @var Job $job */
        $job = Job::query()->findOrFail($payload['job_id']);
        /** @var CandidateProfile $profile */
        $profile = CandidateProfile::query()->findOrFail($payload['profile_id']);

        if ($job->user_id !== $payload['user_id'] || $profile->user_id !== $payload['user_id']) {
            throw ValidationException::withMessages([
                'job_id' => 'The supplied job/profile pair does not belong to the authenticated user.',
            ]);
        }

        if (isset($payload['job_match_id'])) {
            /** @var JobMatch $jobMatch */
            $jobMatch = JobMatch::query()->findOrFail($payload['job_match_id']);

            if ((int) $jobMatch->job_id !== (int) $payload['job_id'] || (int) $jobMatch->profile_id !== (int) $payload['profile_id']) {
                throw ValidationException::withMessages([
                    'job_match_id' => 'The supplied match does not belong to the given job/profile pair.',
                ]);
            }
        }

        return [
            'job_id' => $payload['job_id'],
            'user_id' => $payload['user_id'],
            'profile_id' => $payload['profile_id'],
            'tailored_resume_id' => $payload['tailored_resume_id'] ?? null,
            'status' => $payload['status'] ?? 'draft',
            'applied_at' => $payload['applied_at'] ?? null,
            'follow_up_date' => $payload['follow_up_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'company_response' => $payload['company_response'] ?? null,
            'interview_date' => $payload['interview_date'] ?? null,
        ];
    }
}
