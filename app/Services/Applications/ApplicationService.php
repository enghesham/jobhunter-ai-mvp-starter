<?php

namespace App\Services\Applications;

use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Matching\Domain\Models\JobMatch;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): Application
    {
        if (isset($payload['job_match_id'])) {
            /** @var JobMatch $jobMatch */
            $jobMatch = JobMatch::query()->findOrFail($payload['job_match_id']);

            if ((int) $jobMatch->job_id !== (int) $payload['job_id'] || (int) $jobMatch->profile_id !== (int) $payload['profile_id']) {
                throw ValidationException::withMessages([
                    'job_match_id' => 'The supplied match does not belong to the given job/profile pair.',
                ]);
            }
        }

        return Application::create([
            'job_id' => $payload['job_id'],
            'profile_id' => $payload['profile_id'],
            'tailored_resume_id' => $payload['tailored_resume_id'] ?? null,
            'status' => $payload['status'] ?? 'draft',
            'applied_at' => $payload['applied_at'] ?? null,
            'follow_up_date' => $payload['follow_up_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'company_response' => $payload['company_response'] ?? null,
            'interview_date' => $payload['interview_date'] ?? null,
        ]);
    }
}
