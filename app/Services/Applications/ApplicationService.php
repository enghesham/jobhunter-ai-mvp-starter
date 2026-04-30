<?php

namespace App\Services\Applications;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Domain\Models\ApplicationEvent;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Matching\Domain\Models\JobMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): Application
    {
        $data = $this->validatedPayload($payload);

        /** @var Application $application */
        $application = DB::transaction(function () use ($data): Application {
            $application = Application::create($data);

            $this->logEvent(
                $application,
                ApplicationEventType::ApplicationCreated,
                'Application pipeline record created.',
                [
                    'status' => $application->status,
                ],
            );

            if ($application->status !== ApplicationStatus::Draft->value) {
                $this->logEvent(
                    $application,
                    ApplicationEventType::StatusChanged,
                    sprintf('Application stage changed from %s to %s.', ApplicationStatus::Draft->value, $application->status),
                    [
                        'from' => ApplicationStatus::Draft->value,
                        'to' => $application->status,
                    ],
                );
            }

            if ($application->tailored_resume_id) {
                $this->logEvent(
                    $application,
                    ApplicationEventType::ResumeLinked,
                    'Tailored resume linked to the application.',
                    [
                        'tailored_resume_id' => $application->tailored_resume_id,
                    ],
                );
            }

            return $application;
        });

        return $application->fresh(['job', 'profile', 'tailoredResume', 'events']);
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

        $originalStatus = $application->status;
        $originalResumeId = $application->tailored_resume_id;

        DB::transaction(function () use ($application, $data, $originalStatus, $originalResumeId): void {
            $application->update($data);

            if ($originalStatus !== $application->status) {
                $this->logEvent(
                    $application,
                    ApplicationEventType::StatusChanged,
                    sprintf('Application stage changed from %s to %s.', $originalStatus, $application->status),
                    [
                        'from' => $originalStatus,
                        'to' => $application->status,
                    ],
                );
            }

            if ((int) $originalResumeId !== (int) $application->tailored_resume_id && $application->tailored_resume_id) {
                $this->logEvent(
                    $application,
                    ApplicationEventType::ResumeLinked,
                    'Tailored resume linked to the application.',
                    [
                        'tailored_resume_id' => $application->tailored_resume_id,
                    ],
                );
            }
        });

        return $application->fresh(['job', 'profile', 'tailoredResume', 'events']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addEvent(Application $application, array $payload): ApplicationEvent
    {
        $occurredAt = isset($payload['occurred_at']) ? Carbon::parse((string) $payload['occurred_at']) : now();
        $type = ApplicationEventType::from((string) $payload['type']);
        $note = $payload['note'] ?? null;
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        return DB::transaction(function () use ($application, $occurredAt, $type, $note, $metadata): ApplicationEvent {
            $event = $this->logEvent($application, $type, $note, $metadata, $occurredAt);
            $this->applyEventSideEffects($application, $type, $metadata, $note, $occurredAt);

            return $event->fresh();
        });
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
            'status' => $this->normalizeStatus($payload['status'] ?? ApplicationStatus::Draft->value),
            'applied_at' => $payload['applied_at'] ?? null,
            'follow_up_date' => $payload['follow_up_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'company_response' => $payload['company_response'] ?? null,
            'interview_date' => $payload['interview_date'] ?? null,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return $status === 'interview'
            ? ApplicationStatus::Interviewing->value
            : $status;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function logEvent(
        Application $application,
        ApplicationEventType $type,
        ?string $note = null,
        array $metadata = [],
        ?Carbon $occurredAt = null,
    ): ApplicationEvent {
        return $application->events()->create([
            'user_id' => $application->user_id,
            'type' => $type->value,
            'note' => $note,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function applyEventSideEffects(
        Application $application,
        ApplicationEventType $type,
        array $metadata,
        ?string $note,
        Carbon $occurredAt,
    ): void {
        $updates = [];

        switch ($type) {
            case ApplicationEventType::AppliedManually:
                $updates['status'] = ApplicationStatus::Applied->value;
                $updates['applied_at'] = $application->applied_at ?: $occurredAt;
                break;
            case ApplicationEventType::InterviewScheduled:
                $updates['status'] = ApplicationStatus::Interviewing->value;
                $updates['interview_date'] = $metadata['interview_date'] ?? $application->interview_date ?? $occurredAt->toISOString();
                break;
            case ApplicationEventType::FollowUpScheduled:
                if (! empty($metadata['follow_up_date'])) {
                    $updates['follow_up_date'] = $metadata['follow_up_date'];
                }
                break;
            case ApplicationEventType::ResponseReceived:
                $updates['company_response'] = $note ?? ($metadata['company_response'] ?? $application->company_response);
                break;
            case ApplicationEventType::OfferReceived:
                $updates['status'] = ApplicationStatus::Offer->value;
                break;
            case ApplicationEventType::Rejected:
                $updates['status'] = ApplicationStatus::Rejected->value;
                break;
            case ApplicationEventType::Archived:
                $updates['status'] = ApplicationStatus::Archived->value;
                break;
            default:
                break;
        }

        if ($updates !== []) {
            $originalStatus = $application->status;

            $application->update($updates);

            if (isset($updates['status']) && $updates['status'] !== $originalStatus) {
                $this->logEvent(
                    $application,
                    ApplicationEventType::StatusChanged,
                    sprintf('Application stage changed from %s to %s.', $originalStatus, $application->status),
                    [
                        'from' => $originalStatus,
                        'to' => $application->status,
                        'triggered_by' => $type->value,
                    ],
                    $occurredAt,
                );
            }
        }
    }
}
