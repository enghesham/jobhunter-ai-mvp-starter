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
            'source_id' => $source->id,
        ];

        /** @var Job|null $job */
        $job = Job::query()
            ->where(function ($query) use ($data, $source, $hash) {
                if ($data->externalId) {
                    $query->where(function ($externalIdQuery) use ($data, $source) {
                        $externalIdQuery
                            ->where('source_id', $source->id)
                            ->where('external_id', $data->externalId);
                    });
                    if ($data->applyUrl) {
                        $query->orWhere('apply_url', $data->applyUrl);
                    }

                    return;
                }

                if ($data->applyUrl) {
                    $query->where('apply_url', $data->applyUrl);

                    return;
                }

                $query->where('hash', $hash);
            })
            ->first();

        if (! $job) {
            $job = Job::create($values);

            return ['job' => $job, 'created' => true, 'changed' => true];
        }

        $job->fill($values);
        $changed = $job->isDirty();
        $job->save();

        return ['job' => $job, 'created' => false, 'changed' => $changed];
    }
}
