<?php

namespace App\Services\JobIngestion;

use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\JobIngestion\DTO\NormalizedJobData;
use App\Services\JobParsing\JobDescriptionCleaner;

class JobUpsertService
{
    public function __construct(private readonly JobDescriptionCleaner $cleaner)
    {
    }

    /**
     * @return array{job: Job, created: bool, changed: bool}
     */
    public function upsert(JobSource $source, NormalizedJobData $data): array
    {
        $hash = $data->externalId
            ? hash('sha256', "{$source->id}|{$data->externalId}")
            : $data->fingerprint();
        $jobFingerprint = $data->jobFingerprint();
        $sourceHash = $data->sourceHash();

        $job = $this->findExistingJob($source, $data, $jobFingerprint, $sourceHash, $hash);

        $values = [
            'external_id' => $data->externalId,
            'user_id' => $source->user_id,
            'company_name' => $data->companyName,
            'title' => $data->title,
            'location' => $data->location,
            'is_remote' => $data->isRemote,
            'remote_type' => $data->remoteType,
            'employment_type' => $data->employmentType,
            'description_raw' => $data->descriptionRaw,
            'description_clean' => $this->cleaner->clean($data->descriptionRaw ?: ''),
            'apply_url' => $data->applyUrl,
            'raw_payload' => $data->rawPayload,
            'salary_text' => $data->salaryText,
            'posted_at' => $data->postedAt,
            'status' => 'new',
            'hash' => $hash,
            'job_fingerprint' => $jobFingerprint,
            'source_hash' => $sourceHash,
            'source_id' => $source->id,
        ];

        if (! $job) {
            $job = Job::create($values);

            return ['job' => $job, 'created' => true, 'changed' => true];
        }

        if ($job->source_id !== $source->id) {
            $values['source_id'] = $job->source_id;
            $values['external_id'] = $job->external_id ?: $data->externalId;
        }

        $job->fill($values);
        $changed = $job->isDirty();
        $job->save();

        return ['job' => $job, 'created' => false, 'changed' => $changed];
    }

    private function findExistingJob(
        JobSource $source,
        NormalizedJobData $data,
        string $jobFingerprint,
        string $sourceHash,
        string $hash,
    ): ?Job {
        $baseQuery = Job::query()->where('user_id', $source->user_id);

        if ($data->externalId) {
            $job = (clone $baseQuery)
                ->where('source_id', $source->id)
                ->where('external_id', $data->externalId)
                ->first();

            if ($job) {
                return $job;
            }
        }

        if ($data->applyUrl) {
            $job = (clone $baseQuery)
                ->where('apply_url', $data->applyUrl)
                ->first();

            if ($job) {
                return $job;
            }
        }

        $job = (clone $baseQuery)
            ->where('source_hash', $sourceHash)
            ->first();

        if ($job) {
            return $job;
        }

        $job = (clone $baseQuery)
            ->where('job_fingerprint', $jobFingerprint)
            ->first();

        if ($job) {
            return $job;
        }

        return Job::query()
            ->where('hash', $hash)
            ->first();
    }
}
