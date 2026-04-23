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

        $attributes = [
            'source_id' => $source->id,
            'hash' => $hash,
        ];

        $values = [
            'external_id' => $data->externalId,
            'company_name' => $data->companyName,
            'title' => $data->title,
            'location' => $data->location,
            'remote_type' => $data->remoteType,
            'employment_type' => $data->employmentType,
            'description_raw' => $data->descriptionRaw,
            'description_clean' => $this->cleaner->clean($data->descriptionRaw ?: ''),
            'apply_url' => $data->applyUrl,
            'salary_text' => $data->salaryText,
            'posted_at' => $data->postedAt,
            'status' => 'new',
        ];

        /** @var Job|null $job */
        $job = Job::query()->where($attributes)->first();

        if (! $job) {
            $job = Job::create($attributes + $values);

            return ['job' => $job, 'created' => true, 'changed' => true];
        }

        $job->fill($values);
        $changed = $job->isDirty();
        $job->save();

        return ['job' => $job, 'created' => false, 'changed' => $changed];
    }
}
